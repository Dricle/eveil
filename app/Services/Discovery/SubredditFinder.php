<?php

namespace App\Services\Discovery;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Turns a handful of topic keywords into real, currently-existing subreddits
 * `DiscoveryPlanner` can safely be told to probe - one mechanical, no-AI
 * lookup, the same shape `WebsiteFinder` already uses to verify a guess
 * before it is trusted.
 *
 * Reddit's own subreddit search is a dead end (`reddit.com` is locked `other`
 * in `known_hosts`, and the endpoint now answers with a login shell, not
 * JSON). Arctic Shift's `/api/subreddits/search?subreddit_prefix=` - the
 * same base URL `RedditSource` already uses - is a name-prefix match, not a
 * full-text one, so a topic word is only as good as the subreddit names it
 * actually shares a prefix with; good enough for the common case
 * ("saas" -> r/SaaS, r/SaaSy), not a substitute for a real semantic search.
 */
class SubredditFinder
{
    /** A community this small is not worth a probe - dead or near-dead. */
    private const MIN_SUBSCRIBERS = 1000;

    /** How many verified subreddits one profile keeps, across all its topics. */
    private const MAX_RESULTS = 8;

    private const PER_TOPIC_LIMIT = 10;

    /**
     * @param  array<int, string>  $topics
     * @return array<int, array{name: string, subscribers: int, description: string}>
     */
    public function find(array $topics): array
    {
        /** @var Collection<string, array{name: string, subscribers: int, description: string}> $found */
        $found = new Collection;

        foreach ($topics as $topic) {
            $topic = trim($topic);

            if ($topic === '') {
                continue;
            }

            foreach ($this->search($topic) as $subreddit) {
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
     * @return array<int, array{name: string, subscribers: int, description: string}>
     */
    private function search(string $topic): array
    {
        try {
            $response = Http::timeout((int) config('eveil.sources.reddit.timeout'))
                ->withHeaders(['User-Agent' => (string) config('eveil.crawl.user_agent')])
                ->get(rtrim((string) config('eveil.sources.reddit.url'), '/').'/api/subreddits/search', [
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
            // NSFW alone is not excluded: the adult industry is a legitimate
            // market like any other, and Eveil has no stated vertical
            // exclusion. Quarantine is a different, stronger signal - Reddit's
            // own flag for genuinely extreme or harmful content - and stays.
            ->filter(fn (array $subreddit): bool => ($subreddit['quarantine'] ?? false) === false
                && (int) ($subreddit['subscribers'] ?? 0) >= self::MIN_SUBSCRIBERS
                && is_string($subreddit['display_name'] ?? null))
            ->map(fn (array $subreddit): array => [
                'name' => (string) $subreddit['display_name'],
                'subscribers' => (int) ($subreddit['subscribers'] ?? 0),
                'description' => is_string($subreddit['public_description'] ?? null)
                    ? mb_substr($subreddit['public_description'], 0, 200)
                    : '',
            ])
            ->values()
            ->all();
    }
}
