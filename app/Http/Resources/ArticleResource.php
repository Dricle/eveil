<?php

namespace App\Http\Resources;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Article
 */
class ArticleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source_type' => $this->source_type->value,
            'source_ref' => $this->source_ref,
            'evidence' => $this->evidence,
            'title' => $this->title,
            'meta_description' => $this->meta_description,
            'body' => $this->body,
            'language' => $this->language,
            'status' => $this->status->value,
            'rejection_reason' => $this->rejection_reason,
            'published_url' => $this->published_url,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
