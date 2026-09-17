<?php

namespace App\Services\Discovery\Sources;

use App\Ai\Agents\RedditThreadTriage;
use App\Services\Discovery\Candidate;
use App\Services\Discovery\Sources\Traits\ReportsFailures;
use App\Support\CurrentProject;
use App\Support\Settings;
use App\Support\Url;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Reddit itself is a locked `other` host in `known_hosts` (never a company,
 * never a list of companies) and is out of reach for a plain fetch besides:
 * this source never touches reddit.com. It reads public posts AND comments
 * through Arctic Shift, a free, key-less mirror of Reddit's own search API
 * (arctic-shift.photon-reddit.com), and an item is only ever a candidate for
 * what it LINKS TO or NAMES, never for itself.
 *
 * Two passes. First, free: a link post's own URL, or the first raw link in
 * the text, checked against a denylist - `productUrl()`, unchanged from the
 * mechanical-only version of this source. Whatever that misses (a comment
 * reply naming a product with no link, a self-post describing one) is batched
 * into ONE model call, `RedditThreadTriage`, because telling "this person is
 * talking about their own product" from "this person is answering a
 * question" is a reading-comprehension job a regex cannot do. A model call
 * inside a source is unusual - every other source is plain HTTP - but it is
 * still metered like any agent call (`laravel/ai`'s middleware opens the
 * `agent_runs` row itself, no explicit pending row needed) and `RunProbe`
 * never has to know.
 */
class RedditSource implements DiscoverySourceInterface
{
    /**
     * Hosts a link post, comment, or self-text link routinely points at that
     * are never the product itself: reddit's own media, and the two sites
     * nearly every launch post embeds regardless of what it is about.
     */
    private const NOT_A_PRODUCT = [
        'reddit.com', 'redd.it', 'i.redd.it', 'v.redd.it',
        'imgur.com', 'youtube.com', 'youtu.be',
    ];

    public function __construct(private Settings $settings) {}

    use ReportsFailures;

    public function name(): string
    {
        return 'reddit';
    }

    /**
     * @param  array{subreddit?: string, query?: string}  $probe
     * @return Collection<int, Candidate>
     */
    public function search(array $probe): Collection
    {
        $subreddit = trim((string) ($probe['subreddit'] ?? ''));

        if ($subreddit === '') {
            return new Collection;
        }

        $query = trim((string) ($probe['query'] ?? ''));

        $items = new Collection([
            ...$this->fetchItems('posts', ['subreddit' => $subreddit, 'query' => $query]),
            ...$this->fetchItems('comments', ['subreddit' => $subreddit, 'query' => $query]),
        ]);

        $resolved = new Collection;
        $unresolved = new Collection;

        foreach ($items as $item) {
            $url = $this->productUrl($item);

            if ($url !== null) {
                $resolved->push($this->toCandidate($item, $url));
            } else {
                $unresolved->push($item);
            }
        }

        return $resolved->merge($this->triage($unresolved))->values();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<int, array<string, mixed>>
     */
    private function fetchItems(string $endpoint, array $params): array
    {
        try {
            $response = Http::timeout((int) config('eveil.sources.reddit.timeout'))
                ->withHeaders(['User-Agent' => (string) config('eveil.crawl.user_agent')])
                ->get(rtrim((string) config('eveil.sources.reddit.url'), '/')."/api/{$endpoint}/search", array_filter([
                    ...$params,
                    'limit' => $this->settings->int('sources.reddit.per_query'),
                ]));
        } catch (Throwable $e) {
            $this->failed("{$endpoint}: {$e->getMessage()}");

            return [];
        }

        if (! $response->successful()) {
            $this->failed("{$endpoint}: HTTP {$response->status()}");

            return [];
        }

        /** @var array<int, array<string, mixed>> */
        return $response->json('data') ?? [];
    }

    /**
     * Everything the mechanical pass could not resolve a link for, read in
     * one batched call and turned into candidates: a real product URL when
     * the author's own post history has one, evidence for a human to check
     * when it does not.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, Candidate>
     */
    private function triage(Collection $items): Collection
    {
        if ($items->isEmpty()) {
            return new Collection;
        }

        $batch = $items->take($this->settings->int('sources.reddit.triage_batch'));

        /** @var Collection<int, array{permalink: string, author: string, text: string}> $payload */
        $payload = $batch->map(fn (array $item): array => [
            'permalink' => $this->permalink($item),
            'author' => (string) ($item['author'] ?? 'unknown'),
            'text' => mb_substr(trim(($item['title'] ?? '').' '.$this->text($item)), 0, 1200),
        ])->filter(fn (array $row): bool => $row['permalink'] !== '')->values();

        if ($payload->isEmpty()) {
            return new Collection;
        }

        try {
            $response = (new RedditThreadTriage(app(CurrentProject::class)->getOrFail(), $payload))->triage();
        } catch (Throwable) {
            // A triage that fails must not fail the probe: whatever the
            // mechanical pass already resolved is still worth keeping.
            return new Collection;
        }

        /** @var array<int, array{permalink?: string, is_candidate?: bool, product_identifier?: string, reason?: string}> $verdicts */
        $verdicts = $response->structured['items'] ?? [];

        $byPermalink = $batch->keyBy(fn (array $item): string => $this->permalink($item));

        return (new Collection($verdicts))
            ->filter(fn (array $verdict): bool => $verdict['is_candidate'] ?? false)
            ->map(function (array $verdict) use ($byPermalink): ?Candidate {
                $item = $byPermalink->get(trim((string) ($verdict['permalink'] ?? '')));

                return $item === null ? null : $this->fromVerdict($item, $verdict);
            })
            ->filter()
            ->values();
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array{product_identifier?: string, reason?: string}  $verdict
     */
    private function fromVerdict(array $item, array $verdict): Candidate
    {
        $author = (string) ($item['author'] ?? '');
        $identifier = trim((string) ($verdict['product_identifier'] ?? ''));
        $reason = trim((string) ($verdict['reason'] ?? ''));
        $permalink = $this->permalink($item);

        // One cheap, bounded shot at the real site before falling back to
        // evidence: a name someone posted under is often linked somewhere
        // else in their own recent activity, even when this one thread has
        // no URL in it at all.
        $url = $author !== '' ? $this->fromAuthorHistory($author) : null;

        if ($url !== null) {
            return new Candidate(
                name: $identifier !== '' ? $identifier : (Url::host($url) ?? $url),
                website: $url,
                source: $this->name(),
                sourceUrl: $permalink,
                facts: array_filter([
                    'reddit_subreddit' => $item['subreddit'] ?? null,
                    'reddit_author' => $author !== '' ? $author : null,
                    'reddit_reason' => $reason !== '' ? $reason : null,
                ]),
            );
        }

        return new Candidate(
            name: $identifier !== '' ? $identifier : "u/{$author}",
            website: null,
            source: $this->name(),
            sourceUrl: $permalink,
            facts: array_filter([
                // Deliberately generic, not `reddit_evidence`: any no-website
                // source can set this to be kept with nothing else to go on
                // (`DiscoveryJob::queueQualifications()`), not just this one.
                'evidence' => mb_substr(trim(($item['title'] ?? '').' '.$this->text($item)), 0, 500) ?: null,
                'reddit_subreddit' => $item['subreddit'] ?? null,
                'reddit_author' => $author !== '' ? $author : null,
                'reddit_reason' => $reason !== '' ? $reason : null,
            ]),
        );
    }

    /**
     * Recent posts only, stops at the first real link: a bounded look at
     * public activity to resolve one ambiguous mention, not a trawl through
     * someone's whole history.
     */
    private function fromAuthorHistory(string $author): ?string
    {
        foreach ($this->fetchItems('posts', ['author' => $author]) as $post) {
            $url = $this->productUrl($post);

            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function toCandidate(array $item, string $url): Candidate
    {
        $title = is_string($item['title'] ?? null) ? trim($item['title']) : '';

        return new Candidate(
            name: $title !== '' ? $title : (Url::host($url) ?? $url),
            website: $url,
            source: $this->name(),
            sourceUrl: $this->permalink($item) ?: $url,
            facts: array_filter([
                'reddit_subreddit' => $item['subreddit'] ?? null,
                'reddit_snippet' => mb_substr($this->text($item), 0, 500) ?: null,
                'reddit_author' => $item['author'] ?? null,
            ]),
        );
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function productUrl(array $item): ?string
    {
        if (($item['is_self'] ?? true) === false) {
            $linked = is_string($item['url'] ?? null) ? Url::normalize($item['url']) : null;

            if ($linked !== null && ! $this->isNotAProduct($linked)) {
                return $linked;
            }
        }

        $text = $this->text($item);

        if (! preg_match_all('#https?://\S+#i', $text, $matches)) {
            return null;
        }

        foreach ($matches[0] as $match) {
            $url = Url::normalize(rtrim($match, '.,)'));

            if ($url !== null && ! $this->isNotAProduct($url)) {
                return $url;
            }
        }

        return null;
    }

    /**
     * A post's body lives under `selftext`, a comment's under `body` - the
     * one shape difference between the two Arctic Shift endpoints.
     *
     * @param  array<string, mixed>  $item
     */
    private function text(array $item): string
    {
        $text = $item['selftext'] ?? $item['body'] ?? null;

        return is_string($text) ? $text : '';
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function permalink(array $item): string
    {
        return is_string($item['permalink'] ?? null) ? 'https://www.reddit.com'.$item['permalink'] : '';
    }

    private function isNotAProduct(string $url): bool
    {
        $host = Url::host($url) ?? '';

        foreach (self::NOT_A_PRODUCT as $denied) {
            if ($host === $denied || str_ends_with($host, ".{$denied}")) {
                return true;
            }
        }

        return false;
    }
}
