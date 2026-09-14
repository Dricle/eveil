<?php

namespace App\Ai\Tools;

use App\Models\DiscoveryRun;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Read-only: how a discovery run is going, or how the last one ended, so the
 * model can answer "how did that search go" without a screen open.
 */
class GetDiscoveryRunStatus implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return 'Reports the status and counters of a discovery run: the latest one for this project, or for one target_profile_id if given.';
    }

    public function handle(Request $request): Stringable|string
    {
        $run = DiscoveryRun::query()
            ->where('project_id', $this->project->id)
            ->when(
                $request->integer('target_profile_id'),
                fn ($query, int $profileId) => $query->where('target_profile_id', $profileId),
            )
            ->latest('started_at')
            ->first();

        if ($run === null) {
            return 'No discovery run has been started yet.';
        }

        return (string) json_encode([
            'id' => $run->id,
            'status' => $run->status->value,
            'started_at' => $run->started_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'queries_used' => $run->queries_used,
            'candidates_found' => $run->candidates_found,
            'qualified_count' => $run->qualified_count,
            'error' => $run->error,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'target_profile_id' => $schema->integer()
                ->description('Look up the run for this target profile instead of the project-wide latest one.'),
        ];
    }
}
