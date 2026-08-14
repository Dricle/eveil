<?php

namespace App\Services\Discovery\Sources;

use App\Services\Discovery\Candidate;
use App\Services\Discovery\Sources\Traits\ReportsFailures;
use App\Support\Settings;
use App\Support\Url;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Self-hosted SearXNG: free, no API key, same code in both editions.
 * The trade-off is that it is a meta-search engine — upstream engines rate-limit
 * it, so a query returning nothing is normal and must never fail a run.
 *
 * Returns every result it is given and judges none of them. There used to be a
 * hardcoded list of aggregator domains filtered out right here, which was wrong
 * twice over: the list could never be complete, and it deleted the most
 * valuable results of all, since a directory page is hundreds of businesses
 * rather than one. `HostRegistry` decides what a host is, once per host ever.
 */
class WebSearchSource implements DiscoverySourceInterface
{
    public function __construct(private Settings $settings) {}

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
            ->take($this->settings->int('sources.searxng.per_query'))
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

        if ($url === null) {
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
}
