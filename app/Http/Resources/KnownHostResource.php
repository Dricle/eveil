<?php

namespace App\Http\Resources;

use App\Models\KnownHost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A learned host as the registry screen shows it: what we decided, why, what it
 * has actually produced, and whether a human has settled the matter.
 *
 * @mixin KnownHost
 */
class KnownHostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'host' => $this->host,
            'kind' => $this->kind->value,
            'reason' => $this->reason,
            'harvest_status' => $this->harvest_status?->value,
            'pages_harvested' => $this->pages_harvested,
            'businesses_found' => $this->businesses_found,
            'is_locked' => $this->is_locked,
            // A stale verdict is re-judged rather than trusted, so the screen
            // shows when it was last settled.
            'last_verified_at' => $this->last_verified_at->toIso8601String(),
            'last_harvested_at' => $this->last_harvested_at?->toIso8601String(),
        ];
    }
}
