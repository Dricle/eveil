<?php

namespace App\Http\Resources;

use App\Models\SocialPost;
use App\Support\SocialPostProgress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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
            'variant' => $this->variant?->value,
            'evidence' => $this->evidence,
            'body' => $this->body,
            // Served by the app, never the disk's own URL: the disk may be
            // private, and the route's project lookup is the access check.
            'image_url' => $this->image_media_id === null ? null : $this->imageUrl($this->image_media_id),
            // Every image drawn for this draft, the picked one flagged, so the
            // user can compare and switch before publishing.
            'images' => $this->whenLoaded('media', fn () => $this->getMedia('images')->map(fn (Media $image): array => [
                'id' => $image->id,
                'url' => $this->imageUrl($image->id),
                'selected' => $image->id === $this->image_media_id,
            ])->values()->all()),
            // A new image queued or being drawn for this draft.
            'drawing_image' => $this->platform->requiresImage() && app(SocialPostProgress::class)->isDrawing($this->resource),
            'status' => $this->status->value,
            'rejection_reason' => $this->rejection_reason,
            'social_account' => $this->whenLoaded('socialAccount', fn () => $this->socialAccount === null ? null : [
                'id' => $this->socialAccount->id,
                'handle' => $this->socialAccount->handle,
                'display_name' => $this->socialAccount->display_name,
            ]),
            'url' => $this->url,
            'published_at' => $this->published_at?->toIso8601String(),
            'last_error' => $this->last_error,
            'promoted_at' => $this->promoted_at?->toIso8601String(),
            'likes_count' => $this->likes_count,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function imageUrl(int $imageId): string
    {
        return route('social.posts.images.show', ['project' => $this->project->slug, 'social_post' => $this->id, 'image' => $imageId]);
    }
}
