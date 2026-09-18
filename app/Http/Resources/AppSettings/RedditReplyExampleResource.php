<?php

namespace App\Http\Resources\AppSettings;

use App\Models\RedditReplyExample;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RedditReplyExample
 */
class RedditReplyExampleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'source' => $this->source,
            'added_by' => $this->addedBy?->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
