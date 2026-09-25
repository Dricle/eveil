<?php

namespace App\Actions;

use App\Enums\SocialAccountStatus;
use App\Enums\SocialPlatform;
use App\Enums\SocialPostFrequency;
use App\Jobs\GenerateSocialPost;
use App\Models\Project;
use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Builder;

/**
 * Which projects are due a new X or Bluesky post right now, one cadence per
 * network. Called by `eveil:social-generate-due`, same shape as
 * `GenerateDueLinkedinPosts`: each due project and network becomes one
 * queued job.
 *
 * Bluesky needs a working account granted to the project, like LinkedIn. X
 * needs nothing: the user posts it by hand.
 */
class GenerateDueSocialPosts
{
    /**
     * How many generations were queued.
     */
    public function handle(): int
    {
        $queued = 0;

        foreach (SocialPlatform::cases() as $platform) {
            Project::query()
                ->where($platform->frequencyColumn(), '!=', SocialPostFrequency::Off->value)
                ->whereNotNull($platform->nextPostColumn())
                ->where($platform->nextPostColumn(), '<=', now())
                ->when($platform->publishesThroughApi(), fn (Builder $projects) => $projects->whereHas(
                    'socialAccounts',
                    /** @param  Builder<SocialAccount>  $accounts */
                    fn (Builder $accounts) => $accounts->where('platform', $platform)->where('status', SocialAccountStatus::Active)
                ))
                ->each(function (Project $project) use ($platform, &$queued): void {
                    GenerateSocialPost::dispatch($project, $platform);

                    /** @var SocialPostFrequency $frequency */
                    $frequency = $project->getAttribute($platform->frequencyColumn());

                    // Advanced regardless of what the job writes, same
                    // reasoning as the LinkedIn cadence.
                    $project->update([$platform->nextPostColumn() => now()->addDays($frequency->days())]);

                    $queued++;
                });
        }

        return $queued;
    }
}
