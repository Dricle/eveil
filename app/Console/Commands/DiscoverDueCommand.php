<?php

namespace App\Console\Commands;

use App\Actions\ContinueDiscovery;
use App\Enums\AutonomyLevel;
use App\Models\Project;
use App\Support\CurrentProject;
use Illuminate\Console\Command;

/**
 * A discovery run that already succeeded is a budget cap hit, not a market
 * exhausted: nothing gets searched for again unless something starts a second
 * run. This is that something, one tick at a time.
 *
 * Safe to run as often as the schedule likes: `ContinueDiscovery` refuses a
 * profile that already has a run in flight or was diagnosed as the wrong
 * target, so a re-run never duplicates or widens something it shouldn't.
 */
class DiscoverDueCommand extends Command
{
    protected $signature = 'eveil:discover-due';

    protected $description = 'Start the next discovery run for every target profile ready for one';

    public function handle(ContinueDiscovery $continue, CurrentProject $currentProject): int
    {
        $total = 0;

        Project::query()->each(function (Project $project) use ($continue, $currentProject, &$total): void {
            // Same reasoning as eveil:enrol-due: supervised means the user
            // decides WHEN, and a tick that started a search behind them would
            // take that decision away.
            if ($project->autonomy_level === AutonomyLevel::Supervised) {
                return;
            }

            if ($project->hasReachedLeadLimit() || $project->hasReachedDailyLeadLimit()) {
                return;
            }

            $total += $currentProject->run($project, fn (): int => $continue->handle($project));
        });

        $this->info($total === 0
            ? 'Nothing ready to search for.'
            : "Started {$total} discovery run(s).");

        return self::SUCCESS;
    }
}
