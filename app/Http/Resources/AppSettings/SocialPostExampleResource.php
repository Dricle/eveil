<?php

namespace App\Http\Resources\AppSettings;

use App\Models\SocialPostExample;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SocialPostExample
 */
class SocialPostExampleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'platform' => $this->platform->value,
            'body' => $this->body,
            'source' => $this->source->value,
            'added_by' => $this->addedBy?->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
