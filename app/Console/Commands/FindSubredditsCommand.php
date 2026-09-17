<?php

namespace App\Console\Commands;

use App\Actions\FindSubreddits;
use App\Models\TargetProfile;
use App\Support\CurrentProject;
use Illuminate\Console\Command;

/**
 * Backfills `criteria.subreddits` on a profile that predates this feature -
 * an agent-derived profile gets this for free from `DeriveTargetProfiles`,
 * but a human-authored one (built by hand, or through Evie, never through
 * `TargetProfileDeriver`) needs it run once explicitly.
 */
class FindSubredditsCommand extends Command
{
    protected $signature = 'eveil:find-subreddits {target_profile : Target profile id}';

    protected $description = 'Resolve a target profile\'s Reddit topics into real, verified subreddits';

    public function handle(FindSubreddits $findSubreddits, CurrentProject $currentProject): int
    {
        $targetProfile = TargetProfile::find((int) $this->argument('target_profile'));

        if ($targetProfile === null) {
            $this->components->error("No target profile with id [{$this->argument('target_profile')}].");

            return self::FAILURE;
        }

        $targetProfile = $currentProject->run(
            $targetProfile->project,
            fn (): TargetProfile => $findSubreddits->handle($targetProfile),
        );

        $subreddits = $targetProfile->criteria['subreddits'] ?? [];

        if ($subreddits === []) {
            $this->components->warn("No real subreddit resolved for [{$targetProfile->name}].");

            return self::SUCCESS;
        }

        foreach ($subreddits as $subreddit) {
            $this->components->twoColumnDetail(
                "r/{$subreddit['name']}",
                number_format((int) $subreddit['subscribers']).' subscribers',
            );
        }

        return self::SUCCESS;
    }
}
