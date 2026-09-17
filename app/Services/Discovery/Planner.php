<?php

namespace App\Services\Discovery;

use App\Ai\Agents\DiscoveryPlanner;
use App\Enums\DiscoveryRunOrigin;
use App\Models\AgentRun;
use App\Models\TargetProfile;
use Illuminate\Support\Collection;

/**
 * Where to look for a target profile, decided once and explained before
 * anything executes. The plan is the only model call in the search half of a
 * run: everything it produces is put to sources by plain PHP.
 */
class Planner
{
    /** How many past runs the planner gets to read. Enough to notice a
     * pattern (a source that keeps coming back blocked), cheap enough that a
     * profile searched for months never balloons the prompt. */
    private const HISTORY_RUNS = 5;

    /**
     * @param  Collection<string, array{qualified: int, avg_fit: float}>|null  $productiveHosts
     * @return array{explanation: ?string, probes: array<int, array{source: string, probe: array<string, mixed>}>}
     */
    public function plan(
        TargetProfile $targetProfile,
        int $maxProbes,
        ?AgentRun $run = null,
        ?string $guidance = null,
        bool $isPivot = false,
        ?Collection $productiveHosts = null,
    ): array {
        $history = $targetProfile->discoveryRuns()
            ->where('origin', DiscoveryRunOrigin::Search)
            ->whereNotNull('finished_at')
            ->latest('id')
            ->limit(self::HISTORY_RUNS)
            ->with('tasks')
            ->get()
            ->reverse()
            ->values();

        $agent = new DiscoveryPlanner(
            $targetProfile->project,
            $targetProfile,
            $maxProbes,
            $guidance,
            $isPivot,
            $history,
            $productiveHosts ?? new Collection,
        );

        if ($run !== null) {
            $agent->recordInto($run);
        }

        return $agent->plan();
    }
}
