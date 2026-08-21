<?php

namespace App\Console\Commands;

use App\Actions\EnrolCampaign;
use App\Enums\AutonomyLevel;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Project;
use App\Support\CurrentProject;
use Illuminate\Console\Command;

/**
 * Putting the people found since into the sequences that are already running.
 *
 * Without this a campaign only ever looks once, at the moment it is started,
 * and every contact discovered afterwards waits for somebody to notice and
 * toggle it off and on again. Discovery runs for hours and contact extraction
 * lands in waves, so "afterwards" is the normal case, not the edge one.
 *
 * Safe to run as often as the schedule likes: enrolment already refuses anyone
 * in a live sequence, and the database has the last word through the
 * one-live-campaign-per-lead index.
 */
class EnrolDueCommand extends Command
{
    protected $signature = 'eveil:enrol-due';

    protected $description = 'Add newly reachable people to the sequences already running';

    public function handle(EnrolCampaign $enrol, CurrentProject $currentProject): int
    {
        $total = 0;

        Project::query()->each(function (Project $project) use ($enrol, $currentProject, &$total): void {
            // The supervised setting means the user decides WHEN, not only
            // who: starting a campaign by hand is the whole of their control,
            // and a tick that enrolled behind them would take it away.
            if ($project->autonomy_level === AutonomyLevel::Supervised) {
                return;
            }

            $currentProject->run($project, function () use ($enrol, &$total): void {
                Campaign::query()
                    ->where('status', CampaignStatus::Active)
                    ->each(function (Campaign $campaign) use ($enrol, &$total): void {
                        $total += $enrol->handle($campaign);
                    });
            });
        });

        $this->info($total === 0
            ? 'Nobody new to enrol.'
            : "Enrolled {$total} person(s).");

        return self::SUCCESS;
    }
}
