<?php

namespace App\Http\Resources;

use App\Models\SocialPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SocialPost
 */
class SocialPostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'platform' => $this->platform->value,
            'source_type' => $this->source_type->value,
            'evidence' => $this->evidence,
            'body' => $this->body,
            'status' => $this->status->value,
            'rejection_reason' => $this->rejection_reason,
            'social_account' => $this->whenLoaded('socialAccount', fn () => $this->socialAccount === null ? null : [
                'id' => $this->socialAccount->id,
                'handle' => $this->socialAccount->handle,
            ]),
            'url' => $this->url,
            'published_at' => $this->published_at?->toIso8601String(),
            'last_error' => $this->last_error,
            'promoted_at' => $this->promoted_at?->toIso8601String(),
            'likes_count' => $this->likes_count,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
