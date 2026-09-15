<?php

namespace App\Actions;

use App\Enums\LinkedinAccountStatus;
use App\Enums\LinkedinPostFrequency;
use App\Jobs\GenerateLinkedinPost;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;

/**
 * The daily tick: which projects are due a new LinkedIn post. Mirrors
 * `DispatchDueSends`'s shape - nothing here writes a post itself, each due
 * project becomes one queued job, so a slow model call costs one project's
 * post and never the batch.
 */
class GenerateDueLinkedinPosts
{
    /**
     * How many generations were queued.
     */
    public function handle(): int
    {
        $queued = 0;

        Project::query()
            ->where('linkedin_post_frequency', '!=', LinkedinPostFrequency::Off->value)
            ->whereNotNull('linkedin_next_post_at')
            ->where('linkedin_next_post_at', '<=', now())
            ->whereHas(
                'linkedinAccounts',
                /** @param  Builder<LinkedinAccount>  $accounts */
                fn (Builder $accounts) => $accounts->where('status', LinkedinAccountStatus::Active)
            )
            ->each(function (Project $project) use (&$queued): void {
                GenerateLinkedinPost::dispatch($project);

                // Advanced regardless of what the job finds: a quiet project
                // must not hammer the check daily. If nothing is worth
                // posting the job simply writes no row, and the next attempt
                // is the next cadence tick, not tomorrow.
                $project->update(['linkedin_next_post_at' => now()->addDays($project->linkedin_post_frequency->days())]);

                $queued++;
            });

        return $queued;
    }
}
