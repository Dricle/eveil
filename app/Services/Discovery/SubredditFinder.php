<?php

namespace App\Services\Discovery;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Turns a target profile's Reddit topics into real, currently-existing
 * subreddits `DiscoveryPlanner` can safely be told to probe - mechanical,
 * no-AI, the same shape `WebsiteFinder` already uses to verify a guess
 * before it is trusted. Two independent ways to find a NAME, one way to
 * trust it.
 *
 * Reddit's own subreddit search is a dead end (`reddit.com` is locked `other`
 * in `known_hosts`, and the endpoint now answers with a login shell, not
 * JSON). Arctic Shift's `/api/subreddits/search` - the same base URL
 * `RedditSource` already uses - is name-only: `subreddit_prefix` matches the
 * start of a name, `subreddit` matches one exactly. Neither ever reads post
 * or comment text, so it is also how every candidate below is VERIFIED
 * (subscriber floor, not quarantined) regardless of where the name came from.
 *
 * Two dead ends, both confirmed live before landing on what ships:
 * - Arctic Shift's `/api/posts/search?query=` (searching post text for a
 *   keyword, unscoped) answers 400 - `"'query' query parameter requires one
 *   of: author, subreddit"`. There is no way to search Reddit CONTENT for a
 *   topic without already knowing which subreddit to look in.
 * - Splitting a multi-word topic into its individual words and prefix-
 *   matching each ("agency life" -> "agency", "life") technically works but
 *   measurably makes results WORSE: "life" alone resolves to r/LifeProTips
 *   (22.7M subscribers) and r/lifehacks (14.4M), both outranking the
 *   genuinely relevant hits by subscriber count and burying them. Subscriber
 *   count says nothing about topical relevance.
 *
 * What DOES work, and is where a name actually comes from besides a topic
 * being one already: a real web search (the same SearXNG instance
 * `WebSearchSource` already queries) for "best subreddits about <topics>"
 * reliably surfaces curated listicles ("27 Best Subreddits for SaaS Founders
 * in 2026"), and their SNIPPET TEXT ALONE - no page fetch needed - already
 * names real subreddits: `r/SaaS`, `r/SideProject`, `r/indiehackers` and
 * similar appear directly in SearXNG's `content` field. Extracting `r/word`
 * mentions is a plain regex, no AI, and a false hit (matching something that
 * only looks like `r/word`) costs nothing: it is checked against Reddit
 * before it is trusted, exactly like a topic match or a model's own guess.
 * Confirmed live: this query recovered r/SaaS, r/SideProject, r/startups,
 * r/indiehackers, r/microsaas and a dozen more from real listicles, for a
 * profile that resolved zero subreddits under the old prefix-only search.
 */
class SubredditFinder
{
    /** A community this small is not worth a probe - dead or near-dead. */
    private const MIN_SUBSCRIBERS = 1000;

    /** How many verified subreddits one profile keeps, across every signal. */
    private const MAX_RESULTS = 8;

    private const PER_TOPIC_LIMIT = 10;

    /** Topics folded into one query, most-specific first: too many turns a search engine's ranking to mush. */
    private const QUERY_TOPICS = 3;

    /** Distinct r/-mentions worth the extra verification call, across ALL search results combined. */
    private const MAX_MENTIONS = 15;

    /**
     * @param  array<int, string>  $topics  keywords: resolved by name-prefix match, and folded into one web search
     * @param  array<int, string>  $guesses  candidate exact subreddit names (from the model, or anywhere else): resolved by exact-name lookup
     * @return array<int, array{name: string, subscribers: int, description: string}>
     */
    public function find(array $topics, array $guesses = []): array
    {
        $topics = $this->cleaned($topics);
        $guesses = $this->cleaned($guesses);

        /** @var Collection<string, array{name: string, subscribers: int, description: string}> $found */
        $found = new Collection;

        foreach ($topics as $topic) {
            foreach ($this->searchByPrefix($topic) as $subreddit) {
                $found->put($subreddit['name'], $subreddit);
            }
        }

        $candidates = (new Collection([...$guesses, ...$this->mentionedInWebSearch($topics)]))
            ->unique(fn (string $name): string => mb_strtolower($name));

        foreach ($candidates as $candidate) {
            if ($found->has($candidate)) {
                continue;
            }

            $subreddit = $this->searchByExactName($candidate);

            if ($subreddit !== null && ! $found->has($subreddit['name'])) {
                $found->put($subreddit['name'], $subreddit);
            }
        }

        return $found
            ->sortByDesc('subscribers')
            ->take(self::MAX_RESULTS)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, string>
     */
    private function cleaned(array $values): array
    {
        return array_values(array_filter(array_map('trim', $values), fn (string $value): bool => $value !== ''));
    }

    /**
     * @return array<int, array{name: string, subscribers: int, description: string}>
     */
    private function searchByPrefix(string $topic): array
    {
        try {
            $response = $this->reddit()->get('/api/subreddits/search', [
                'subreddit_prefix' => $topic,
                'limit' => self::PER_TOPIC_LIMIT,
            ]);
        } catch (Throwable) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        /** @var array<int, array<string, mixed>> $subreddits */
        $subreddits = $response->json('data') ?? [];

        return (new Collection($subreddits))
            ->map($this->toSubreddit(...))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array{name: string, subscribers: int, description: string}|null
     */
    private function searchByExactName(string $name): ?array
    {
        try {
            $response = $this->reddit()->get('/api/subreddits/search', [
                'subreddit' => $name,
                'limit' => 1,
            ]);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        /** @var array<int, array<string, mixed>> $subreddits */
        $subreddits = $response->json('data') ?? [];

        return $this->toSubreddit($subreddits[0] ?? []);
    }

    /**
     * A real web search for "best subreddits about <topics>" reliably
     * surfaces curated listicles, and their title/snippet text alone already
     * names real subreddits - no page fetch needed. Every name extracted here
     * is a CANDIDATE only: `find()` verifies it against Reddit the same as
     * any other, so a false regex hit (something that merely looks like
     * `r/word`) is simply dropped rather than trusted.
     *
     * @param  array<int, string>  $topics
     * @return array<int, string>
     */
    private function mentionedInWebSearch(array $topics): array
    {
        if ($topics === []) {
            return [];
        }

        $query = 'best subreddits about '.implode(' and ', array_slice($topics, 0, self::QUERY_TOPICS));

        try {
            $response = Http::timeout((int) config('eveil.sources.searxng.timeout'))
                ->withHeaders(['User-Agent' => (string) config('eveil.crawl.user_agent')])
                ->get(rtrim((string) config('eveil.sources.searxng.url'), '/').'/search', [
                    'q' => $query,
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

        /** @var Collection<string, string> $mentions */
        $mentions = new Collection;

        foreach ($results as $result) {
            $text = trim(
                (is_string($result['title'] ?? null) ? $result['title'] : '').' '
                .(is_string($result['content'] ?? null) ? $result['content'] : '').' '
                .(is_string($result['url'] ?? null) ? $result['url'] : '')
            );

            if (preg_match_all('#(?:reddit\.com/r/|\br/)([A-Za-z0-9_]{2,21})#i', $text, $matches)) {
                foreach ($matches[1] as $name) {
                    if (! $mentions->has(mb_strtolower($name))) {
                        $mentions->put(mb_strtolower($name), $name);
                    }
                }
            }
        }

        return $mentions->values()->take(self::MAX_MENTIONS)->all();
    }

    /**
     * @param  array<string, mixed>  $subreddit
     * @return array{name: string, subscribers: int, description: string}|null
     */
    private function toSubreddit(array $subreddit): ?array
    {
        // NSFW alone is not excluded: the adult industry is a legitimate
        // market like any other, and Eveil has no stated vertical exclusion.
        // Quarantine is a different, stronger signal - Reddit's own flag for
        // genuinely extreme or harmful content - and stays.
        if (($subreddit['quarantine'] ?? false) !== false) {
            return null;
        }

        if ((int) ($subreddit['subscribers'] ?? 0) < self::MIN_SUBSCRIBERS) {
            return null;
        }

        if (! is_string($subreddit['display_name'] ?? null)) {
            return null;
        }

        return [
            'name' => (string) $subreddit['display_name'],
            'subscribers' => (int) ($subreddit['subscribers'] ?? 0),
            'description' => is_string($subreddit['public_description'] ?? null)
                ? mb_substr($subreddit['public_description'], 0, 200)
                : '',
        ];
    }

    private function reddit(): PendingRequest
    {
        return Http::timeout((int) config('eveil.sources.reddit.timeout'))
            ->withHeaders(['User-Agent' => (string) config('eveil.crawl.user_agent')])
            ->baseUrl(rtrim((string) config('eveil.sources.reddit.url'), '/'));
    }
}
