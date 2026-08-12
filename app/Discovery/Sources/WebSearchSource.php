<?php

namespace App\Discovery\Sources;

use App\Discovery\Candidate;
use App\Discovery\Url;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Self-hosted SearXNG: free, no API key, same code in both editions.
 * The trade-off is that it is a meta-search engine — upstream engines rate-limit
 * it, so a query returning nothing is normal and must never fail a run.
 */
class WebSearchSource implements DiscoverySource
{
    use ReportsFailures;

    public function name(): string
    {
        return 'web_search';
    }

    /**
     * @param  array{query?: string, language?: string}  $probe
     * @return Collection<int, Candidate>
     */
    public function search(array $probe): Collection
    {
        $query = trim((string) ($probe['query'] ?? ''));

        if ($query === '') {
            return new Collection;
        }

        try {
            $response = Http::timeout((int) config('eveil.sources.searxng.timeout'))
                ->withHeaders(['User-Agent' => (string) config('eveil.crawl.user_agent')])
                ->get(rtrim((string) config('eveil.sources.searxng.url'), '/').'/search', [
                    'q' => $query,
                    'format' => 'json',
                    'language' => $probe['language'] ?? 'auto',
                ]);
        } catch (Throwable $e) {
            return $this->failed("{$query}: {$e->getMessage()}");
        }

        if (! $response->successful()) {
            return $this->failed("{$query}: HTTP {$response->status()}");
        }

        /** @var array<int, array<string, mixed>> $results */
        $results = $response->json('results') ?? [];

        return (new Collection($results))
            ->take((int) config('eveil.sources.searxng.per_query'))
            ->map(fn (array $result): ?Candidate => $this->toCandidate($result, $query))
            ->filter()
            ->values();
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function toCandidate(array $result, string $query): ?Candidate
    {
        $url = is_string($result['url'] ?? null) ? Url::normalize($result['url']) : null;

        if ($url === null || $this->isAggregator($url)) {
            return null;
        }

        $title = is_string($result['title'] ?? null) ? trim($result['title']) : '';

        return new Candidate(
            name: $title !== '' ? $title : (Url::host($url) ?? $url),
            website: $url,
            source: $this->name(),
            sourceUrl: $url,
            facts: ['query' => $query, 'snippet' => $result['content'] ?? null],
        );
    }

    /**
     * Directories and platforms are how you find companies, not companies you
     * can sell to. Keeping them would fill a run with pages nobody can email.
     */
    private function isAggregator(string $url): bool
    {
        $host = Url::host($url) ?? '';

        foreach ([
            'wikipedia.org', 'facebook.com', 'instagram.com', 'linkedin.com', 'x.com', 'twitter.com',
            'youtube.com', 'tripadvisor.', 'yelp.', 'pagesdor.be', 'pagesjaunes.', 'google.',
            'deliveroo.', 'ubereats.', 'takeaway.com', 'pinterest.', 'tiktok.com', 'amazon.',
            'indeed.', 'glassdoor.', 'crunchbase.com', 'reddit.com', 'medium.com',
        ] as $aggregator) {
            if (str_contains($host, $aggregator)) {
                return true;
            }
        }

        return false;
    }
}
