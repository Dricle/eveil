<?php

namespace App\Jobs\Discovery;

use App\Ai\Agents\DiscoveryPlanner;
use App\Enums\DiscoveryTaskKind;
use App\Models\DiscoveryRun;
use App\Models\DiscoveryTask;
use App\Services\Discovery\Planner;
use RuntimeException;

/**
 * `DiscoveryRun::finishIfIdle()`'s memory: a directory the run only found by
 * chance while searching for something else, that turned out unusually
 * productive, gets one focused follow-up wave before the run closes - rather
 * than being treated as one more result among many and moved past, or waiting
 * for `ContinueDiscovery`'s cadence to maybe try it again in six hours.
 *
 * Never written back to `known_hosts`: whether a host is productive is a fact
 * about THIS target profile, and `known_hosts` is deliberately profile-blind
 * (ADR-033) - a directory that is gold for one profile is noise for another.
 * This node's whole existence is keeping that judgement inside the run that
 * made it.
 */
class ReflectAndExpand extends DiscoveryJob
{
    protected function execute(DiscoveryRun $run, DiscoveryTask $task): array
    {
        $targetProfile = $run->targetProfile;

        if ($targetProfile === null) {
            throw new RuntimeException('The profile this run was started for has been deleted.');
        }

        $productiveHosts = $run->productiveHosts();
        $remaining = max(0, $run->limit('max_queries') - $run->queries_used);

        $plan = app(Planner::class)->plan(
            $targetProfile,
            $remaining,
            $this->meter($task, DiscoveryPlanner::slug()),
            $run->guidance,
            productiveHosts: $productiveHosts,
        );

        $run->update([
            'stats' => [
                ...$run->stats ?? [],
                'plan' => trim(($run->stats['plan'] ?? '')
                    ."\n\nFound ".$productiveHosts->count()." productive host(s) partway through, focused follow-up: {$plan['explanation']}"),
            ],
        ]);

        foreach ($plan['probes'] as $probe) {
            $this->fork($task, DiscoveryTaskKind::Probe, $probe, RunProbe::class);
        }

        return ['probes' => count($plan['probes']), 'productive_hosts' => $productiveHosts->keys()->all()];
    }
}
