<?php

namespace App\Discovery;

/**
 * URL normalisation, kept in one place because the crawl cache and the company
 * dedupe both key on it — two different normalisations would mean two cache
 * entries for one page.
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

        if ($href === '' || str_starts_with($href, '#')) {
            return null;
        }

        foreach (['mailto:', 'tel:', 'javascript:', 'data:'] as $scheme) {
            if (str_starts_with(mb_strtolower($href), $scheme)) {
                return null;
            }
        }

        $base = parse_url($baseUrl);

        if (! isset($base['scheme'], $base['host'])) {
            return null;
        }

        $absolute = match (true) {
            str_starts_with($href, 'http://'), str_starts_with($href, 'https://') => $href,
            str_starts_with($href, '//') => $base['scheme'].':'.$href,
            // A query-only reference keeps the base PATH (RFC 3986 §5.3). Taking
            // its dirname instead turns `?page=2` on /directory/plumbers into
            // /directory?page=2 — which is how pagination silently breaks, and
            // pagination is the whole point of harvesting a listing.
            str_starts_with($href, '?') => $base['scheme'].'://'.$base['host'].($base['path'] ?? '/').$href,
            str_starts_with($href, '/') => $base['scheme'].'://'.$base['host'].$href,
            default => $base['scheme'].'://'.$base['host'].'/'.ltrim(
                rtrim(dirname($base['path'] ?? '/'), '/').'/'.$href, '/'
            ),
        };

        return self::normalize(self::removeDotSegments($absolute));
    }

    /**
     * Collapses `.` and `..` segments. Without it a `../contact` href produces
     * a URL that 404s, and the failed fetch still costs a request and a cache
     * row.
     */
    private static function removeDotSegments(string $url): string
    {
        $previous = '';

        while ($previous !== $url) {
            $previous = $url;
            $url = (string) preg_replace('#/(?!\.\./)[^/]+/\.\./#', '/', $url, 1);
        }

        return str_replace('/./', '/', $url);
    }

    /**
     * Drops the fragment, lowercases the host, and strips a trailing slash so
     * `https://Example.com/about/` and `https://example.com/about` are one URL.
     */
    public static function normalize(string $url): ?string
    {
        $parts = parse_url($url);

        if (! isset($parts['scheme'], $parts['host']) || ! in_array($parts['scheme'], ['http', 'https'], true)) {
            return null;
        }

        $path = rtrim($parts['path'] ?? '', '/');
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return mb_strtolower($parts['scheme'].'://'.$parts['host']).($path === '' ? '/' : $path).$query;
    }

    public static function host(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? mb_strtolower(preg_replace('/^www\./', '', $host) ?? $host) : null;
    }

    public static function path(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) ? $path : '/';
    }
}
