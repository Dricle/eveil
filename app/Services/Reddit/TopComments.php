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
 *
 * This is also the only point in a scan where a comment carries a score worth
 * reading: `OpportunityScanner` pulls a subreddit's NEWEST comments, minutes
 * old and sitting at 1. Sorting one post's own comments is what surfaces the
 * ones people actually upvoted, which is why
 * `App\Actions\HarvestRedditReplyExamples` reads this response instead of
 * fetching anything of its own.
 */
class TopComments
{
    /**
     * @return Collection<int, TopComment> highest score first
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
            ->sortByDesc(fn (array $comment): int => $this->score($comment))
            ->take($limit)
            ->map(fn (array $comment): TopComment => new TopComment(
                id: (string) ($comment['id'] ?? ''),
                body: trim((string) $comment['body']),
                author: (string) ($comment['author'] ?? ''),
                subreddit: is_string($comment['subreddit'] ?? null) ? $comment['subreddit'] : null,
                permalink: is_string($comment['permalink'] ?? null) ? 'https://www.reddit.com'.$comment['permalink'] : '',
                score: $this->score($comment),
            ))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $comment
     */
    private function score(array $comment): int
    {
        return (int) ($comment['score'] ?? $comment['ups'] ?? 0);
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
