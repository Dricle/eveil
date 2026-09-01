<?php

namespace App\Actions;

use App\Enums\DiscoveryTaskKind;
use App\Models\DiscoveryRun;
use App\Support\CurrentProject;

/**
 * The one search currently spending budget, grouped into the three stages
 * the pipeline actually has: an agent call decides where to look, plain PHP
 * fetches the volume, then every candidate is scored against the profile.
 * Task kinds map onto those three rather than being shown one row each,
 * since `classify` (a user-pasted link) is rare enough that a stage of its
 * own would mostly read empty.
 *
 * `DiscoveryRun` scopes itself to the current project via `BelongsToProject`,
 * but only while one is actually SET - a caller outside the `web` middleware
 * group (a command, a job, a future API) that forgets to set one would get
 * every project's running run silently, not an error. `getOrFail()` here
 * turns that into a loud failure instead, so the class stays safe to reuse
 * outside a request that already guarantees the scope.
 *
 * A use case, not a query scoped inline in a controller: the dashboard reads
 * it today, and anywhere else that wants to say "here is what discovery is
 * doing right now" - a Targets/Searches widget, a status API - calls the
 * same class rather than re-deriving the stage grouping.
 */
class SummarizeRunningDiscovery
{
    public function __construct(private CurrentProject $currentProject) {}

    /**
     * @return array<string, mixed>|null
     */
    public function handle(): ?array
    {
        $this->currentProject->getOrFail();

        $run = DiscoveryRun::query()
            ->open()
            ->with('targetProfile')
            ->latest('id')
            ->first();

        if ($run === null) {
            return null;
        }

        $stages = [
            'Search planning' => [DiscoveryTaskKind::Plan],
            'Finding candidates' => [DiscoveryTaskKind::Probe, DiscoveryTaskKind::Harvest, DiscoveryTaskKind::Classify],
            'Qualifying' => [DiscoveryTaskKind::Qualify],
        ];

        $counts = $run->tasks()
            ->selectRaw('kind, status, count(*) as total')
            ->groupBy('kind', 'status')
            ->get()
            ->groupBy('kind');

        return [
            'id' => $run->id,
            'target_profile_name' => $run->targetProfile?->name,
            'status' => $run->status,
            'started_at' => $run->started_at?->toIso8601String(),
            'candidates_found' => $run->candidates_found,
            'max_companies' => $run->budget['max_companies'] ?? null,
            'queries_used' => $run->queries_used,
            'max_queries' => $run->budget['max_queries'] ?? null,
            'stages' => collect($stages)->map(function (array $kinds, string $label) use ($counts): array {
                $rows = $counts->only(array_map(fn ($kind) => $kind->value, $kinds))->flatten(1);
                $total = $rows->sum('total');
                $done = $rows->where('status', 'succeeded')->sum('total')
                    + $rows->where('status', 'failed')->sum('total')
                    + $rows->where('status', 'skipped')->sum('total');
                $running = $rows->where('status', 'running')->sum('total') > 0;

                return [
                    'label' => $label,
                    'done' => $done,
                    'total' => $total,
                    'state' => $total === 0 ? 'waiting' : ($done === $total ? 'done' : ($running || $done > 0 ? 'running' : 'waiting')),
                ];
            })->values(),
        ];
    }
}
