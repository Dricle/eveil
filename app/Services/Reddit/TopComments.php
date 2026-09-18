<?php

namespace App\Services\Reddit;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * A specific post's own highest-scoring comments, read-only against Arctic
 * Shift (the same free mirror `RedditSource`/`SubredditFinder` already use -
 * never Reddit's real API). Two callers, same shape: `SeoThreadFinder`
 * needs it BEFORE triage, to judge whether a thread's existing answers are
 * weak; `App\Jobs\ScanRedditOpportunities` needs it AFTER triage accepts a
 * `subreddit_scan` item, purely as a tone sample for `RedditReplyWriter`
 * (a `seo_thread` candidate already carries its top comments from the first
 * call and never needs a second one).
 */
class TopComments
{
    /**
     * @return Collection<int, string> plain comment bodies, highest score first
     */
    public function for(string $postId, int $limit = 5): Collection
    {
        try {
            $response = Http::timeout((int) config('eveil.sources.reddit.timeout'))
                ->withHeaders(['User-Agent' => (string) config('eveil.crawl.user_agent')])
                ->get(rtrim((string) config('eveil.sources.reddit.url'), '/').'/api/comments/search', [
                    'link_id' => $postId,
                    'limit' => 100,
                ]);
        } catch (Throwable) {
            return new Collection;
        }

        if (! $response->successful()) {
            return new Collection;
        }

        /** @var array<int, array<string, mixed>> $comments */
        $comments = $response->json('data') ?? [];

        return (new Collection($comments))
            ->reject(fn (array $comment): bool => $this->isRemoved($comment))
            ->sortByDesc(fn (array $comment): int => (int) ($comment['score'] ?? $comment['ups'] ?? 0))
            ->take($limit)
            ->map(fn (array $comment): string => trim((string) $comment['body']))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $comment
     */
    private function isRemoved(array $comment): bool
    {
        $body = is_string($comment['body'] ?? null) ? $comment['body'] : '';
        $author = (string) ($comment['author'] ?? '');

        return in_array($body, ['[removed]', '[deleted]'], true) || $author === '[deleted]';
    }
}
