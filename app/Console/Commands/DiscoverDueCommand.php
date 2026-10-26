<?php

namespace App\Console\Commands;

use App\Actions\ContinueDiscovery;
use App\Ai\Agents\DiscoveryPlanner;
use App\Ai\Contracts\SpendGuardInterface;
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

    public function handle(ContinueDiscovery $continue, CurrentProject $currentProject, SpendGuardInterface $spendGuard): int
    {
        $total = 0;

        Project::query()->each(function (Project $project) use ($continue, $currentProject, $spendGuard, &$total): void {
            // Same reasoning as eveil:enrol-due: supervised means the user
            // decides WHEN, and a tick that started a search behind them would
            // take that decision away.
            if ($project->email_autonomy_level === AutonomyLevel::Supervised) {
                return;
            }

            // Email switched off is what pausing the search means: the
            // profiles stay, nothing goes looking for them.
            if (! $project->email_enabled) {
                return;
            }

            if ($project->hasReachedLeadLimit() || $project->hasReachedDailyLeadLimit()) {
                return;
            }

            // The planner would be refused anyway, but only after the run
            // row exists: on prod that left hundreds of failed runs, one per
            // profile every six hours, for an organization that simply had
            // not topped up yet.
            if ($spendGuard->refusal($project, DiscoveryPlanner::slug()) !== null) {
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
