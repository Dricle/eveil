<?php

namespace App\Services\Reddit;

use App\Enums\RedditReplySource;
use App\Models\Project;
use App\Models\RedditReply;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Evergreen Reddit threads that already rank on a search engine for
 * buyer-intent queries ("best X", "X alternative") - a different, arguably
 * better opportunity than a live subreddit post: these threads get read
 * long after they go quiet, by everyone who runs that search, with none of
 * the self-promo-rule tension since the thread exists specifically to ask
 * "what should I use."
 *
 * Queries are auto-derived from the knowledge base, no new UI:
 * `product_category` (a new `WebsiteAnalyst` field) plus the already-
 * existing `competitors` array. Search happens through SearXNG (same infra
 * `SubredditFinder::mentionedInWebSearch()` already uses), scoped with
 * `site:reddit.com`; a matching result is then read through Arctic Shift
 * (`/api/posts/search?url=`, confirmed live) for its own title/selftext,
 * plus its top comments (`TopComments`) both to judge whether the existing
 * answers are weak AND to double as `RedditReplyWriter`'s tone sample later
 * - no second fetch needed for that, unlike a `subreddit_scan` candidate.
 */
class SeoThreadFinder
{
    private const RESULTS_PER_QUERY = 10;

    private const TOP_COMMENTS_LIMIT = 5;

    public function __construct(private TopComments $topComments) {}

    /**
     * @return Collection<int, OpportunityCandidate>
     */
    public function find(Project $project): Collection
    {
        $queries = $this->queries($project);

        if ($queries->isEmpty()) {
            return new Collection;
        }

        $alreadyScanned = RedditReply::query()->pluck('thread_permalink');

        /** @var Collection<string, array{permalink: string, query: string}> $permalinks */
        $permalinks = new Collection;

        foreach ($queries as $query) {
            foreach ($this->search($query) as $permalink) {
                if (! $permalinks->has($permalink) && ! $alreadyScanned->contains($permalink)) {
                    $permalinks->put($permalink, ['permalink' => $permalink, 'query' => $query]);
                }
            }
        }

        return $permalinks->values()
            ->map(fn (array $found): ?OpportunityCandidate => $this->toCandidate($found['permalink'], $found['query']))
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    public function queries(Project $project): Collection
    {
        $knowledgeBase = $project->knowledge_base ?? [];
        $queries = new Collection;

        $category = is_string($knowledgeBase['product_category'] ?? null) ? trim($knowledgeBase['product_category']) : '';

        if ($category !== '') {
            $queries->push("best {$category}");
        }

        $competitors = is_array($knowledgeBase['competitors'] ?? null) ? $knowledgeBase['competitors'] : [];

        foreach ($competitors as $competitor) {
            if (is_string($competitor) && trim($competitor) !== '') {
                $queries->push(trim($competitor).' alternative');
            }
        }

        return $queries;
    }

    /**
     * @return array<int, string> normalized reddit.com permalinks
     */
    private function search(string $query): array
    {
        try {
            $response = Http::timeout((int) config('eveil.sources.searxng.timeout'))
                ->withHeaders(['User-Agent' => (string) config('eveil.crawl.user_agent')])
                ->get(rtrim((string) config('eveil.sources.searxng.url'), '/').'/search', [
                    'q' => "site:reddit.com \"{$query}\"",
                    'format' => 'json',
                    'language' => 'auto',
                ]);
        } catch (Throwable) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        /** @var array<int, array<string, mixed>> $results */
        $results = $response->json('results') ?? [];

        return (new Collection($results))
            ->take(self::RESULTS_PER_QUERY)
            ->map(fn (array $result): ?string => $this->normalizePermalink((string) ($result['url'] ?? '')))
            ->filter()
            ->values()
            ->all();
    }

    private function normalizePermalink(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || ! str_ends_with($host, 'reddit.com')) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || ! str_contains($path, '/comments/')) {
            return null;
        }

        return 'https://www.reddit.com'.rtrim($path, '/').'/';
    }

    private function toCandidate(string $permalink, string $query): ?OpportunityCandidate
    {
        $post = $this->fetchPost($permalink);

        if ($post === null) {
            return null;
        }

        $title = is_string($post['title'] ?? null) ? trim($post['title']) : '';
        $selftext = is_string($post['selftext'] ?? null) ? trim($post['selftext']) : '';
        $id = (string) ($post['id'] ?? '');

        if ($id === '' || $this->isRemoved($title) || $this->isRemoved($selftext)) {
            return null;
        }

        $comments = $this->topComments->for($id, self::TOP_COMMENTS_LIMIT);

        $text = mb_substr(trim(
            "{$title}\n\n{$selftext}\n\n".$comments->map(fn (string $c): string => "> {$c}")->implode("\n\n")
        ), 0, 3000);

        return new OpportunityCandidate(
            permalink: $permalink,
            subreddit: is_string($post['subreddit'] ?? null) ? $post['subreddit'] : null,
            source: RedditReplySource::SeoThread,
            searchQuery: $query,
            author: (string) ($post['author'] ?? ''),
            text: $text,
            threadTitle: $title !== '' ? $title : null,
            postId: $id,
            topComments: $comments->all(),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchPost(string $permalink): ?array
    {
        try {
            $response = Http::timeout((int) config('eveil.sources.reddit.timeout'))
                ->withHeaders(['User-Agent' => (string) config('eveil.crawl.user_agent')])
                ->get(rtrim((string) config('eveil.sources.reddit.url'), '/').'/api/posts/search', [
                    'url' => $permalink,
                ]);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        /** @var array<int, array<string, mixed>> $posts */
        $posts = $response->json('data') ?? [];

        return $posts[0] ?? null;
    }

    private function isRemoved(string $text): bool
    {
        return in_array($text, ['[removed]', '[deleted]', '[ Removed by moderator ]'], true);
    }
}
