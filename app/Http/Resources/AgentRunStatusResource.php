<?php

namespace App\Http\Resources;

use App\Enums\AgentRunStatus;
use App\Models\AgentRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Where a queued agent call has got to, for a screen waiting on it: still
 * queued, running, or failed and why. `since` is when it last changed status,
 * which for a running call is when a worker picked it up.
 *
 * @mixin AgentRun
 */
class AgentRunStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->status->value,
            'since' => $this->updated_at?->toIso8601String(),
            'error' => $this->status === AgentRunStatus::Failed ? $this->error : null,
        ];
    }
}
