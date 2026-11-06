<?php

namespace App\Http\Resources;

use App\Models\Insight;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Insight
 */
class InsightResource extends JsonResource
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
            'source' => $this->source,
            'source_ref' => $this->source_ref,
            'title' => $this->title,
            'angle' => $this->angle,
            'url' => $this->url,
            'details' => $this->details ?? (object) [],
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
