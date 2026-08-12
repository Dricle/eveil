<?php

namespace App\Discovery;

use App\Ai\Agents\ListingExtractor;
use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Reads a directory listing page and returns the businesses on it.
 *
 * The reason this exists: a search engine ranks companies that do SEO, so it
 * finds the ones already being sold to. A directory's page for one trade in one
 * town is not a company, it is two hundred companies — and for a business with
 * no site of its own it is the only place an email is published. The aggregator
 * blocklist used to delete exactly this.
 *
 * The model decides WHERE to harvest; this does the volume. JSON-LD is tried
 * first because it costs nothing to try and is perfect when present — but it is
 * a windfall, not an assumption. The first three real directories tried emitted
 * none of it — one behind bot protection, one 403-ing unknown agents, one a
 * 737 KB JS app — so
 * **the LLM extractor is the normal path and the cost model**.
 *
 * Which is why extraction is cached: `crawled_pages` caches the fetch, not the
 * model call, and testing a directory means running the same pages repeatedly.
 *
 * Stored CSS selectors are the rung between the two and are still not built.
 * They are now worth real money rather than being speculative — learned once
 * from the model's own output, replayed free afterwards — but only once a
 * directory has produced more than once.
 *
 * Not a `DiscoverySource`: that interface is one probe in, one page of results
 * out. A harvest is multi-page and budgeted, so it will become a job when the
 * discovery graph lands.
 */
class ListingHarvester
{
    public function __construct(private PageFetcher $fetcher, private HtmlText $html) {}

    public function harvest(string $url, ?Project $project = null, ?int $maxPages = null): Harvest
    {
        $maxPages ??= (int) config('eveil.sources.directory.max_pages');
        $maxEntities = (int) config('eveil.sources.directory.max_entities');

        /** @var Collection<int, Candidate> $candidates */
        $candidates = new Collection;
        $pages = [];
        $modes = [];
        $seen = [];
        $next = Url::normalize($url);
        $stopped = null;

        while ($next !== null) {
            if (count($pages) >= $maxPages) {
                $stopped = 'page budget';
                break;
            }

            if ($candidates->count() >= $maxEntities) {
                $stopped = 'entity budget';
                break;
            }

            // A directory whose "next" link points back into the set we have
            // already read would otherwise spend the whole budget in a circle.
            if (in_array($next, $pages, true)) {
                $stopped = 'pagination loops';
                break;
            }

            $page = $this->fetcher->fetch($next);

            if ($page === null) {
                $stopped = $pages === [] ? 'page could not be read' : 'next page could not be read';
                break;
            }

            $body = (string) $page->content;
            $pages[] = $next;

            [$found, $mode] = $this->extract($body, $next, $project);
            $modes[] = $mode;

            foreach ($found as $candidate) {
                $key = $this->key($candidate);

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $candidates->push($candidate);
            }

            $next = $this->html->next($body, $next);
        }

        return new Harvest($candidates->take($maxEntities)->values(), $pages, $modes, $stopped);
    }

    /**
     * @return array{0: array<int, Candidate>, 1: string}
     */
    private function extract(string $body, string $url, ?Project $project): array
    {
        $businesses = JsonLd::businesses($body, $url);

        if ($businesses !== []) {
            return [array_map(fn (array $business): Candidate => $this->fromJsonLd($business, $url), $businesses), 'jsonld'];
        }

        if ($project === null) {
            return [[], 'none'];
        }

        return [$this->viaAgent($body, $url, $project), 'llm'];
    }

    /**
     * @param  array{name: string, url: ?string, email: ?string, phone: ?string, address: ?string}  $business
     */
    private function fromJsonLd(array $business, string $listingUrl): Candidate
    {
        // JSON-LD `url` is whatever the directory chose to publish: sometimes
        // the business's own site, sometimes its page in the directory. Only
        // the former is a website we can crawl and dedupe on.
        $url = $business['url'];
        $isOwnSite = $url !== null && Url::host($url) !== Url::host($listingUrl);

        return new Candidate(
            name: $business['name'],
            website: $isOwnSite ? $url : null,
            source: 'directory',
            sourceUrl: $isOwnSite ? $listingUrl : ($url ?? $listingUrl),
            facts: array_filter([
                'email' => $business['email'],
                'phone' => $business['phone'],
                'address' => $business['address'],
                'directory' => Url::host($listingUrl),
            ]),
        );
    }

    /**
     * @return array<int, Candidate>
     */
    private function viaAgent(string $body, string $url, Project $project): array
    {
        $parsed = $this->html->parse($body, $url);

        if ($parsed->isEmpty()) {
            return [];
        }

        $extractor = new ListingExtractor($project);

        // The one call that costs money, so it is paid for once per page. Keyed
        // and expiring like the page cache it mirrors, for the same reason
        // : public content, ICP-independent, safe to share — and it
        // pays off most on the re-runs that testing a directory is made of.
        //
        // The instructions are part of the key so that editing the prompt
        // invalidates what the old prompt produced. A hand-bumped version
        // constant is a version constant somebody forgets to bump, and the
        // symptom — a prompt fix that changes nothing for a week — is nasty
        // to diagnose.
        /** @var array<int, array<string, string>> $businesses */
        $businesses = Cache::remember(
            'listing:'.hash('xxh3', (string) $extractor->instructions()).':'.hash('sha256', $url),
            now()->addDays((int) config('eveil.crawl.cache_ttl_days')),
            fn (): array => $this->ask($extractor, $parsed, $url),
        );

        return collect($businesses)
            ->map(fn (array $business): ?Candidate => $this->fromAgent($business, $url))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function ask(ListingExtractor $extractor, ParsedPage $parsed, string $url): array
    {
        try {
            $response = $extractor->prompt(
                "Directory listing page: {$url}\n\n".mb_substr($parsed->text, 0, 24_000),
            );
        } catch (Throwable) {
            // One unreadable listing must not cost a run everything before it —
            // the same rule the qualification loop learned the hard way.
            return [];
        }

        /** @var array<int, array<string, string>> */
        return $response->structured['businesses'] ?? [];
    }

    /**
     * @param  array<string, string>  $business
     */
    private function fromAgent(array $business, string $listingUrl): ?Candidate
    {
        $name = trim($business['name'] ?? '');

        if ($name === '') {
            return null;
        }

        $website = Url::normalize($this->withScheme(trim($business['website'] ?? '')));
        $detail = Url::resolve(trim($business['detail_url'] ?? ''), $listingUrl);

        // A model asked for "the business's own site" will sometimes hand back
        // the directory's. It is not one, by definition.
        if ($website !== null && Url::host($website) === Url::host($listingUrl)) {
            $website = null;
        }

        return new Candidate(
            name: $name,
            website: $website,
            source: 'directory',
            sourceUrl: $website !== null ? $listingUrl : ($detail ?? $listingUrl),
            facts: array_filter([
                'email' => trim($business['email'] ?? '') ?: null,
                'phone' => trim($business['phone'] ?? '') ?: null,
                'address' => trim($business['address'] ?? '') ?: null,
                'detail_url' => $detail,
                'directory' => Url::host($listingUrl),
            ]),
        );
    }

    private function withScheme(string $url): string
    {
        if ($url === '' || str_starts_with($url, 'http')) {
            return $url;
        }

        return 'https://'.ltrim($url, '/');
    }

    /**
     * Dedupe on the domain when there is one — the same business listed in two
     * categories is one business — and on the name otherwise, since a
     * site-less entry has nothing else to key on.
     */
    private function key(Candidate $candidate): string
    {
        return $candidate->domain() ?? mb_strtolower($candidate->name);
    }
}
