<?php

namespace App\Support;

use League\Uri\Uri as LeagueUri;

/**
 * URL normalisation, kept in one place because the crawl cache and the company
 * dedupe both key on it: two different normalisations would mean two cache
 * entries for one page.
 *
 * Parsing and relative resolution are League\Uri's job, not ours. It ships with
 * the framework (`Illuminate\Support\Uri` is a thin wrapper over the same
 * object) so it costs no dependency, and it implements RFC 3986 properly: dot
 * segments, query-only references, protocol-relative hosts, scheme and host
 * casing, default ports. A hand-rolled version of that was here and got
 * `?page=2` wrong, which is how pagination silently broke.
 *
 * What stays ours is CRAWLER POLICY: which URLs we are willing to follow and
 * what shape we store them in. That is an application decision and does not
 * belong in a URI library.
 */
class Url
{
    /**
     * Resolves a possibly relative href against the page it was found on.
     * Returns null for anything we should not follow.
     */
    public static function resolve(string $href, string $baseUrl): ?string
    {
        $href = trim($href);

        // A bare fragment points at the page we are already on. Resolving it
        // would hand back the same URL and the crawler would count it as a new
        // link to follow.
        if ($href === '' || str_starts_with($href, '#')) {
            return null;
        }

        return self::normalize((string) LeagueUri::parse($href, $baseUrl));
    }

    /**
     * Drops the fragment, lowercases the host, and strips a trailing slash so
     * `https://Example.com/about/` and `https://example.com/about` are one URL.
     *
     * Also where `mailto:`, `tel:`, `javascript:` and `data:` are refused.
     * Not as a hand-kept list of schemes, but because anything that is not
     * http(s) is not a page we can fetch.
     */
    public static function normalize(string $url): ?string
    {
        $uri = LeagueUri::parse($url);

        if ($uri === null || ! in_array($uri->getScheme(), ['http', 'https'], true)) {
            return null;
        }

        $host = $uri->getHost();

        if ($host === null || $host === '') {
            return null;
        }

        $path = rtrim($uri->getPath(), '/');
        $port = $uri->getPort();
        $query = $uri->getQuery();

        return $uri->getScheme().'://'.$host
            .($port === null ? '' : ':'.$port)
            .($path === '' ? '/' : $path)
            .($query === null || $query === '' ? '' : '?'.$query);
    }

    /**
     * Turns what somebody typed into a URL. `example.com` is how people write
     * an address, and demanding the scheme would turn it into a second field
     * to get right. Returns the input untouched when nothing can be made of
     * it, so validation reports what was actually entered.
     */
    public static function fromInput(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return $url;
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        return self::normalize($url) ?? $url;
    }

    /**
     * The host without `www.`, which is a display prefix rather than a
     * different site, and the key companies are deduped on.
     */
    public static function host(string $url): ?string
    {
        $host = LeagueUri::parse($url)?->getHost();

        return $host === null ? null : preg_replace('/^www\./', '', $host);
    }

    public static function path(string $url): string
    {
        $path = LeagueUri::parse($url)?->getPath();

        return $path === null || $path === '' ? '/' : $path;
    }
}
