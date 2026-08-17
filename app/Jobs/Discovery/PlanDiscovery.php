<?php

namespace App\Jobs\Discovery;

use App\Ai\Agents\DiscoveryPlanner;
use App\Enums\DiscoveryRunStatus;
use App\Enums\DiscoveryTaskKind;
use App\Models\DiscoveryRun;
use App\Models\DiscoveryTask;
use App\Services\Discovery\Planner;
use RuntimeException;

/**
 * The one model call in the search half of a run: where to look, and why, said
 * before anything executes. Everything it produces is put to sources by plain
 * PHP.
 */
class PlanDiscovery extends DiscoveryJob
{
    protected function execute(DiscoveryRun $run, DiscoveryTask $task): array
    {
        $targetProfile = $run->targetProfile;

        if ($targetProfile === null) {
            throw new RuntimeException('The profile this run was started for has been deleted.');
        }

        $plan = app(Planner::class)->plan(
            $targetProfile,
            $run->limit('max_queries'),
            $this->meter($task, DiscoveryPlanner::slug()),
        );

        $run->update([
            'status' => DiscoveryRunStatus::Running,
            'stats' => [...$run->stats ?? [], 'plan' => $plan['explanation']],
        ]);

        foreach ($plan['probes'] as $probe) {
            $this->fork($task, DiscoveryTaskKind::Probe, $probe, RunProbe::class);
        }

        return ['probes' => count($plan['probes'])];
    }

    /**
     * With no plan there is nowhere to look, so this is the one node whose
     * failure is the run's.
     */
    protected function failsRun(): bool
    {
        return true;
    }
}
