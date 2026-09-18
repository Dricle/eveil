<?php

namespace App\Actions;

use App\Enums\RedditScanFrequency;
use App\Jobs\ScanRedditOpportunities;
use App\Models\Project;

/**
 * Which projects are due a Reddit opportunity scan right now. Called by
 * `eveil:reddit-scan-due`, scheduled in `routes/console.php`; the cadence
 * lives there, not here. Copy of `GenerateDueLinkedinPosts`'s shape - each
 * due project becomes one queued job, so a slow run costs one project's
 * scan and never the batch.
 *
 * No account-status gate to copy from the LinkedIn version: nothing here
 * depends on a connected account existing, since this feature has none.
 */
class GenerateDueRedditScans
{
    /**
     * How many scans were queued.
     */
    public function handle(): int
    {
        $queued = 0;

        Project::query()
            ->where('reddit_scan_frequency', '!=', RedditScanFrequency::Off->value)
            ->whereNotNull('reddit_next_scan_at')
            ->where('reddit_next_scan_at', '<=', now())
            ->each(function (Project $project) use (&$queued): void {
                ScanRedditOpportunities::dispatch($project);

                // Advanced regardless of what the job finds: a quiet
                // subreddit must not be re-checked on every tick of the
                // scheduled command.
                $project->update(['reddit_next_scan_at' => now()->addDays($project->reddit_scan_frequency->days())]);

                $queued++;
            });

        return $queued;
    }
}
