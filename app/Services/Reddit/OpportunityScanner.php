<?php

namespace App\Services\Reddit;

use App\Enums\RedditReplySource;
use App\Models\Project;
use App\Models\RedditReply;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Live subreddit activity, read-only against Arctic Shift - the same free
 * mirror `RedditSource`/`SubredditFinder` already use. Feeds
 * `App\Ai\Agents\RedditOpportunityTriage` alongside `SeoThreadFinder`'s
 * candidates, both tagged `source` so the job can merge them into one
 * batch.
 *
 * Confirmed live and load-bearing: a heavily-automodded subreddit's newest
 * POSTS are overwhelmingly `[ Removed by moderator ]` placeholders (24/25
 * recent in r/selfhosted at the time this was checked) while its newest
 * COMMENTS are real (14/15) - posts alone would starve this scanner, so
 * both are fetched. `sort=new` 400s ("`sort` must be one of asc, desc");
 * `sort=desc&sort_type=created_utc` is the confirmed-working equivalent.
 */
class OpportunityScanner
{
    private const PER_SUBREDDIT_LIMIT = 25;

    /**
     * @return Collection<int, OpportunityCandidate>
     */
    public function find(Project $project): Collection
    {
        $subreddits = $this->subreddits($project);

        if ($subreddits->isEmpty()) {
            return new Collection;
        }

        $alreadyScanned = RedditReply::query()->pluck('thread_permalink');

        /** @var Collection<int, ?OpportunityCandidate> $candidates */
        $candidates = new Collection;

        foreach ($subreddits as $subreddit) {
            foreach ($this->fetchPosts($subreddit) as $item) {
                $candidates->push($this->fromPost($item));
            }

            foreach ($this->fetchComments($subreddit) as $item) {
                $candidates->push($this->fromComment($item));
            }
        }

        return $candidates
            ->filter()
            ->reject(fn (OpportunityCandidate $candidate): bool => $alreadyScanned->contains($candidate->permalink))
            ->unique(fn (OpportunityCandidate $candidate): string => $candidate->permalink)
            ->values();
    }

    /**
     * Every subreddit any of the project's target profiles resolved
     * (`App\Actions\FindSubreddits`), deduped by name - reused, never
     * re-derived.
     *
     * @return Collection<int, string>
     */
    private function subreddits(Project $project): Collection
    {
        $names = new Collection;

        foreach ($project->targetProfiles as $profile) {
            $subreddits = $profile->criteria['subreddits'] ?? [];

            if (! is_array($subreddits)) {
                continue;
            }

            foreach ($subreddits as $subreddit) {
                $name = is_array($subreddit) ? ($subreddit['name'] ?? null) : null;

                if (is_string($name) && $name !== '' && ! $names->contains($name)) {
                    $names->push($name);
                }
            }
        }

        return $names;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchPosts(string $subreddit): array
    {
        return $this->fetch('posts', $subreddit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchComments(string $subreddit): array
    {
        return $this->fetch('comments', $subreddit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetch(string $endpoint, string $subreddit): array
    {
        try {
            $response = Http::timeout((int) config('eveil.sources.reddit.timeout'))
                ->withHeaders(['User-Agent' => (string) config('eveil.crawl.user_agent')])
                ->get(rtrim((string) config('eveil.sources.reddit.url'), '/')."/api/{$endpoint}/search", [
                    'subreddit' => $subreddit,
                    'sort' => 'desc',
                    'sort_type' => 'created_utc',
                    'limit' => self::PER_SUBREDDIT_LIMIT,
                ]);
        } catch (Throwable) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        /** @var array<int, array<string, mixed>> */
        return $response->json('data') ?? [];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function fromPost(array $item): ?OpportunityCandidate
    {
        $title = is_string($item['title'] ?? null) ? trim($item['title']) : '';
        $selftext = is_string($item['selftext'] ?? null) ? trim($item['selftext']) : '';
        $author = (string) ($item['author'] ?? '');

        if ($this->isRemoved($title) || $this->isRemoved($selftext) || $author === '[deleted]') {
            return null;
        }

        $permalink = $this->permalink($item);
        $id = (string) ($item['id'] ?? '');

        if ($permalink === '' || $id === '') {
            return null;
        }

        return new OpportunityCandidate(
            permalink: $permalink,
            subreddit: is_string($item['subreddit'] ?? null) ? $item['subreddit'] : null,
            source: RedditReplySource::SubredditScan,
            searchQuery: null,
            author: $author,
            text: mb_substr(trim("{$title} {$selftext}"), 0, 1500),
            threadTitle: $title !== '' ? $title : null,
            postId: $id,
        );
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function fromComment(array $item): ?OpportunityCandidate
    {
        $body = is_string($item['body'] ?? null) ? trim($item['body']) : '';
        $author = (string) ($item['author'] ?? '');

        if ($this->isRemoved($body) || $author === '[deleted]') {
            return null;
        }

        $permalink = $this->permalink($item);
        $linkId = is_string($item['link_id'] ?? null) ? str_replace('t3_', '', $item['link_id']) : '';

        if ($permalink === '' || $linkId === '') {
            return null;
        }

        return new OpportunityCandidate(
            permalink: $permalink,
            subreddit: is_string($item['subreddit'] ?? null) ? $item['subreddit'] : null,
            source: RedditReplySource::SubredditScan,
            searchQuery: null,
            author: $author,
            text: mb_substr($body, 0, 1500),
            threadTitle: null,
            postId: $linkId,
        );
    }

    private function isRemoved(string $text): bool
    {
        return in_array($text, ['[removed]', '[deleted]', '[ Removed by moderator ]'], true);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function permalink(array $item): string
    {
        return is_string($item['permalink'] ?? null) ? 'https://www.reddit.com'.$item['permalink'] : '';
    }
}
