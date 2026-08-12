<?php

namespace App\Discovery;

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
    public function __construct(private PageFetcher $fetcher, private HtmlText $html) {}

    /**
     * @return Collection<int, ParsedPage>
     */
    public function crawl(string $seedUrl, ?int $maxPages = null): Collection
    {
        $maxPages = $maxPages ?? (int) config('eveil.crawl.max_pages');
        $seed = Url::normalize($seedUrl);

        if ($seed === null) {
            return new Collection;
        }

        $home = $this->fetcher->fetch($seed);

        if ($home === null) {
            return new Collection;
        }

        $parsed = $this->html->parse((string) $home->content, $seed);

        /** @var Collection<int, ParsedPage> $pages */
        $pages = new Collection([$parsed]);

        foreach ($this->pick($parsed->links, $seed, $maxPages - 1) as $url) {
            $page = $this->fetcher->fetch($url);

            if ($page === null) {
                continue;
            }

            $pages->push($this->html->parse((string) $page->content, $url));
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

        /** @var array<int, string> $priority */
        $priority = config('eveil.crawl.priority_paths');

        foreach ($priority as $needle) {
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
