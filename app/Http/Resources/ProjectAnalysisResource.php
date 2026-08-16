<?php

namespace App\Http\Resources;

use App\Models\ProjectAnalysis;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProjectAnalysis
 */
class ProjectAnalysisResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'error' => $this->error,
            'failures' => $this->failures ?? [],
            'pages_read' => is_array($this->raw['pages'] ?? null) ? count($this->raw['pages']) : 0,
            'finished_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
