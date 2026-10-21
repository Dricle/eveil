<?php

namespace App\Actions;

use App\Ai\Agents\AdStrategist;
use App\Enums\AgentRunStatus;
use App\Models\AgentRun;
use App\Models\Project;
use App\Services\Ads\AdPerformanceReport;

/**
 * One daily review of a project's ads: gather the report, hand it to the
 * agent, let it act through its tools. Nothing to review, no model call: a
 * project needs a monthly ads budget and an account to spend it on.
 */
class ReviewAds
{
    public function __construct(private AdPerformanceReport $report) {}

    public function handle(Project $project, ?AgentRun $run = null): void
    {
        if (! $project->runsAds()) {
            // The budget was removed between queueing and now: close the row
            // the caller opened rather than leave it pending.
            $run?->update(['status' => AgentRunStatus::Aborted, 'error' => 'No monthly ads budget, or no ad account to spend it on.']);

            return;
        }

        $agent = new AdStrategist(
            $project,
            $this->report->build($project),
            $project->targetProfiles()->where('is_active', true)->get(),
        );

        if ($run !== null) {
            $agent->recordInto($run);
        }

        $agent->review();
    }
}
