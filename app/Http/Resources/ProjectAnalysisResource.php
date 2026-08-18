<?php

namespace App\Http\Resources;

use App\Enums\AnalysisStatus;
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
            // Both counts are written page by page while the crawl runs, so the
            // screen can show progress instead of an empty page for minutes.
            'pages_read' => is_array($this->raw['pages'] ?? null) ? count($this->raw['pages']) : 0,
            'pages_planned' => (int) ($this->raw['max_pages'] ?? 0),
            'running' => in_array($this->status, [AnalysisStatus::Pending, AnalysisStatus::Running], true),
            'finished_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
