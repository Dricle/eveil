<?php

namespace App\Http\Resources;

use App\Models\RedditReply;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RedditReply
 */
class RedditReplyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subreddit' => $this->subreddit,
            'thread_permalink' => $this->thread_permalink,
            'thread_title' => $this->thread_title,
            'source' => $this->source->value,
            'search_query' => $this->search_query,
            'angle' => $this->angle->value,
            'evidence' => $this->evidence,
            'body' => $this->body,
            'status' => $this->status->value,
            'rejection_reason' => $this->rejection_reason,
            'published_at' => $this->published_at?->toIso8601String(),
            'comment_permalink' => $this->comment_permalink,
            'score' => $this->score,
            'stats_checked_at' => $this->stats_checked_at?->toIso8601String(),
            'promoted_at' => $this->promoted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
