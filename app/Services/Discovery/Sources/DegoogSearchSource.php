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
 * Self-hosted degoog: a second free, no-API-key search aggregator, run
 * alongside `WebSearchSource` rather than instead of it. Upstream engines
 * rate-limit a meta-search instance, so a query returning nothing from one is
 * normal and indistinguishable from a dead instance without a second source
 * to cross-check against.
 *
 * `/api/search?format=json` returns the same SearXNG-shaped `results[]`
 * document that `WebSearchSource::toCandidate()` already reads, so the two
 * sources share everything but the endpoint and the `source` tag.
 */
class DegoogSearchSource implements DiscoverySourceInterface
{
    public function __construct(private Settings $settings) {}

    use ReportsFailures;

    public function name(): string
    {
        return 'degoog';
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
            $response = Http::timeout((int) config('eveil.sources.degoog.timeout'))
                ->withHeaders(['User-Agent' => (string) config('eveil.crawl.user_agent')])
                ->get(rtrim((string) config('eveil.sources.degoog.url'), '/').'/api/search', [
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
            ->take($this->settings->int('sources.degoog.per_query'))
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
