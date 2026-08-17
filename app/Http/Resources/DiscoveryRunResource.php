<?php

namespace App\Http\Resources;

use App\Models\DiscoveryRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One search for companies: what it was told to spend, what it has spent, and
 * how it ended. The nodes come with it only on the run's own page.
 *
 * @mixin DiscoveryRun
 */
class DiscoveryRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'running' => ! $this->status->isTerminal(),
            'diagnosis' => $this->diagnosis?->value,
            'error' => $this->error,
            'profile' => $this->targetProfile?->name,
            'profile_id' => $this->target_profile_id,
            'plan' => $this->stats['plan'] ?? null,
            'budget' => $this->budget,
            'spent' => [
                'queries' => $this->queries_used,
                'candidates' => $this->candidates_found,
                'pages' => $this->pages_used,
                'qualified' => $this->qualified_count,
            ],
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'tasks' => DiscoveryTaskResource::collection($this->whenLoaded('tasks')),
        ];
    }
}
