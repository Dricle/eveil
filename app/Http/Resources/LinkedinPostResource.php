<?php

namespace App\Http\Resources;

use App\Models\LinkedinPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LinkedinPost
 */
class LinkedinPostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source_type' => $this->source_type->value,
            'variant' => $this->variant?->value,
            'evidence' => $this->evidence,
            'body' => $this->body,
            'status' => $this->status->value,
            'urn' => $this->urn,
            'published_at' => $this->published_at?->toIso8601String(),
            'last_error' => $this->last_error,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
