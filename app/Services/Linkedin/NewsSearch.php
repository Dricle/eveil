<?php

namespace App\Services\Linkedin;

use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Plain SearXNG lookup for recent industry news to react to - the same
 * self-hosted, no-API-key, both-editions infrastructure
 * `App\Services\Discovery\Sources\WebSearchSource` already calls, not a new
 * vendor. No LLM call here: `LinkedinPostWriter` judges relevance itself,
 * this only fetches candidates.
 *
 * An empty or failed search is normal, same resilience note as
 * `WebSearchSource`'s own docblock: upstream engines rate-limit a meta-search
 * instance, so this must never throw and must never fail the run that calls
 * it.
 */
class NewsSearch
{
    /**
     * @return Collection<int, array{title: string, url: string, snippet: string}>
     */
    public function recent(Project $project): Collection
    {
        $query = $this->query($project);

        if ($query === '') {
            return new Collection;
        }

        try {
            $response = Http::timeout((int) config('eveil.sources.searxng.timeout'))
                ->withHeaders(['User-Agent' => (string) config('eveil.crawl.user_agent')])
                ->get(rtrim((string) config('eveil.sources.searxng.url'), '/').'/search', [
                    'q' => $query,
                    'format' => 'json',
                    'language' => $project->default_language ?? 'auto',
                    'time_range' => 'week',
                ]);
        } catch (Throwable) {
            return new Collection;
        }

        if (! $response->successful()) {
            return new Collection;
        }

        /** @var array<int, array<string, mixed>> $results */
        $results = $response->json('results') ?? [];

        return (new Collection($results))
            ->take(5)
            ->map(fn (array $result): ?array => $this->toCandidate($result))
            ->filter()
            ->values();
    }

    /**
     * Built from what the knowledge base and target profile already say
     * about the sector: no new field to maintain, and it degrades to an
     * empty (skipped) search on a project with neither yet.
     */
    private function query(Project $project): string
    {
        $knowledgeBase = $project->knowledge_base ?? [];
        $positioning = is_string($knowledgeBase['positioning'] ?? null) ? $knowledgeBase['positioning'] : '';
        $competitors = is_array($knowledgeBase['competitors'] ?? null) ? $knowledgeBase['competitors'] : [];

        $terms = array_filter([$positioning, ...array_slice($competitors, 0, 2)]);

        return trim(implode(' ', $terms) !== '' ? implode(' ', $terms).' news' : '');
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array{title: string, url: string, snippet: string}|null
     */
    private function toCandidate(array $result): ?array
    {
        $url = is_string($result['url'] ?? null) ? $result['url'] : null;
        $title = is_string($result['title'] ?? null) ? trim($result['title']) : '';

        if ($url === null || $title === '') {
            return null;
        }

        return [
            'title' => $title,
            'url' => $url,
            'snippet' => is_string($result['content'] ?? null) ? $result['content'] : '',
        ];
    }
}
