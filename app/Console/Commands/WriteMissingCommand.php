<?php

namespace App\Console\Commands;

use App\Actions\WriteMissingCampaigns;
use App\Enums\AutonomyLevel;
use App\Models\Project;
use App\Support\CurrentProject;
use Illuminate\Console\Command;

/**
 * A sequence for every segment that has none, on the projects left to run
 * themselves.
 *
 * Hourly rather than every few minutes, and only on the autonomous setting:
 * this is the most expensive single call the product makes, and target profiles
 * appear once or twice in a project's life rather than continuously. An hour is
 * invisible to somebody who has asked not to be involved.
 *
 * Self-healing by construction, which is why it is a tick and not a hook on
 * derivation: it also covers a profile added by hand and a project moved to
 * autonomous long after its segments were worked out.
 */
class WriteMissingCommand extends Command
{
    protected $signature = 'eveil:write-missing';

    protected $description = 'Write a sequence for every segment that has none, on autonomous projects';

    public function handle(WriteMissingCampaigns $write, CurrentProject $currentProject): int
    {
        $queued = 0;

        Project::query()
            ->where('autonomy_level', AutonomyLevel::Autonomous)
            ->each(function (Project $project) use ($write, $currentProject, &$queued): void {
                $queued += $currentProject->run($project, fn () => $write->handle($project)->count());
            });

        $this->info($queued === 0
            ? 'Every segment already has a sequence.'
            : "Queued {$queued} sequence(s).");

        return self::SUCCESS;
    }
}
