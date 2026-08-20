<?php

namespace App\Http\Resources;

use App\Enums\DiscoveryTaskKind;
use App\Models\DiscoveryTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One node of the graph, as the run screen draws it: what it was asked to do,
 * how it went, what it produced and what the model call cost.
 *
 * @mixin DiscoveryTask
 */
class DiscoveryTaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'status' => $this->status->value,
            'subject' => $this->subject(),
            'result' => $this->result ?? [],
            'error' => $this->error,
            'attempts' => $this->attempts,
            'tokens' => $this->agentRun === null
                ? null
                : $this->agentRun->tokens_in + $this->agentRun->tokens_out,
            'duration_ms' => $this->started_at === null || $this->finished_at === null
                ? null
                : $this->started_at->diffInMilliseconds($this->finished_at),
        ];
    }

    /**
     * What this node is about, in the words of whoever queued it. The query,
     * the directory, the company. Without it a run is forty identical rows.
     */
    private function subject(): string
    {
        $payload = $this->payload ?? [];

        return match ($this->kind) {
            DiscoveryTaskKind::Plan => 'Where to look, and why',
            DiscoveryTaskKind::Probe => (string) ($payload['probe']['query'] ?? $payload['probe']['area'] ?? $payload['source'] ?? ''),
            DiscoveryTaskKind::Harvest => (string) ($payload['host'] ?? ''),
            DiscoveryTaskKind::Qualify => (string) ($payload['domain'] ?? $payload['name'] ?? ''),
        };
    }
}
