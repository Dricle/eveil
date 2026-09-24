<?php

namespace App\Http\Resources;

use App\Models\Idea;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Idea
 */
class IdeaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'source_ref' => $this->source_ref,
            'title' => $this->title,
            'angle' => $this->angle,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
