<?php

namespace App\Services\Discovery;

use App\Support\HtmlText;
use App\Support\ParsedPage;
use App\Support\Settings;
use App\Support\Url;
use Closure;
use Illuminate\Support\Collection;

/**
 * Bounded crawl of one site: fetch the homepage, then follow the handful of
 * links most likely to say what the product is, who it is for and what it
 * costs. A homepage alone rarely answers any of those.
 *
 * Bounded on purpose: page count is part of a run's hard budget.
 */
class SiteCrawler
{
    /**
     * Paths worth reading first. A heuristic rather than a setting — the
     * homepage rarely says what a product costs or who it is for.
     */
    private const PRIORITY_PATHS = [
        'about', 'a-propos', 'apropos', 'over-ons',
        'pricing', 'prix', 'tarifs', 'tarieven',
        'product', 'produit', 'features', 'fonctionnalites', 'solutions',
        'services', 'customers', 'clients', 'cases', 'use-cases',
        'contact',
    ];

    public function __construct(private PageFetcher $fetcher, private HtmlText $html, private Settings $settings) {}

    /**
     * @param  Closure|null  $onProgress  called after every page attempt with
     *                                    the pages read so far, what failed and
     *                                    the ceiling — a crawl takes minutes,
     *                                    and a screen showing nothing until it
     *                                    ends looks broken rather than busy.
     *
     * Signature: `fn (Collection<int, ParsedPage> $pages, array<int, array{url: string, reason: string}> $failures, int $maxPages)`
     * @return Collection<int, ParsedPage>
     */
    public function crawl(string $seedUrl, ?int $maxPages = null, ?Closure $onProgress = null): Collection
    {
        $maxPages = $maxPages ?? $this->settings->int('crawl.max_pages');
        $seed = Url::normalize($seedUrl);

        /** @var Collection<int, ParsedPage> $pages */
        $pages = new Collection;

        /** @var array<int, array{url: string, reason: string}> $failures */
        $failures = [];

        $report = function () use (&$pages, &$failures, $maxPages, $onProgress): void {
            if ($onProgress !== null) {
                $onProgress($pages, $failures, $maxPages);
            }
        };

        if ($seed === null) {
            $failures[] = ['url' => $seedUrl, 'reason' => 'Not a usable address.'];
            $report();

            return $pages;
        }

        $home = $this->fetcher->fetch($seed, $reason);

        if ($home === null) {
            $failures[] = ['url' => $seed, 'reason' => $reason ?? 'Nothing came back.'];
            $report();

            return $pages;
        }

        $parsed = $this->html->parse((string) $home->content, $seed);

        $pages->push($parsed);
        $report();

        foreach ($this->pick($parsed->links, $seed, $maxPages - 1) as $url) {
            $page = $this->fetcher->fetch($url, $reason);

            // A page that will not open costs the analysis a slice of the site
            // rather than the whole run, so the crawl keeps going and says so
            // at the end.
            if ($page === null) {
                $failures[] = ['url' => $url, 'reason' => $reason ?? 'Nothing came back.'];
            } else {
                $pages->push($this->html->parse((string) $page->content, $url));
            }

            $report();
        }

        return $pages;
    }

    /**
     * Same host only, shallow paths first, and the paths that actually carry
     * product information ahead of everything else.
     *
     * @param  array<int, string>  $links
     * @return array<int, string>
     */
    private function pick(array $links, string $seed, int $limit): array
    {
        if ($limit < 1) {
            return [];
        }

        $host = Url::host($seed);

        return collect($links)
            ->filter(fn (string $url): bool => Url::host($url) === $host && $url !== $seed)
            ->unique()
            ->sortBy(fn (string $url): array => [-$this->score($url), mb_strlen($url)])
            ->take($limit)
            ->values()
            ->all();
    }

    private function score(string $url): int
    {
        $path = mb_strtolower(Url::path($url));

        if ($path === '/' || $path === '') {
            return 0;
        }

        foreach (self::PRIORITY_PATHS as $needle) {
            if (str_contains($path, $needle)) {
                return 10 - (int) min(9, substr_count(trim($path, '/'), '/'));
            }
        }

        // Account pages render nothing useful without a session — restogo.be
        // spent two of eleven page slots on /login and /register for zero
        // characters. Rank them below everything else.
        foreach (['login', 'register', 'signin', 'sign-in', 'signup', 'sign-up', 'connexion',
            'inscription', 'account', 'compte', 'cart', 'panier', 'checkout', 'logout'] as $deadEnd) {
            if (str_contains($path, $deadEnd)) {
                return -10;
            }
        }

        // Anything that smells like an archive is noise for a product portrait.
        foreach (['blog', 'news', 'article', 'post', 'tag', 'category', 'author', '.pdf'] as $noise) {
            if (str_contains($path, $noise)) {
                return -5;
            }
        }

        return 1;
    }
}
