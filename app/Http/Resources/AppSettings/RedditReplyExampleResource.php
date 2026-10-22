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
            // Set on a harvested row only: it is somebody else's comment, so
            // the screen shows who wrote it, where, what it scored and why it
            // was kept, rather than asking a superadmin to trust the row.
            'comment_permalink' => $this->comment_permalink,
            'comment_author' => $this->comment_author,
            'subreddit' => $this->subreddit,
            'score' => $this->score,
            'harvest_reason' => $this->harvest_reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
