<?php

namespace App\Services\Video;

use App\Services\Discovery\PageFetcher;
use App\Support\Url;
use DOMDocument;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * What a brand looks like, read from its own material, so its videos wear
 * its colours and type rather than a house style: the logo's dominant
 * colours, and the colours and fonts its homepage declares.
 *
 * Both are hints for `VideoDirector`, which also sees the logo itself: the
 * homepage's stylesheets are not fetched, so only what its HTML carries
 * (a theme colour, inline and embedded styles, utility classes with a
 * colour in them, a web font link) is read.
 *
 * Read once and kept: the site's on the project when its website is
 * analysed (`Project::$brand_style`), the logo's on the logo itself.
 */
class BrandStyle
{
    private const GENERIC_FONTS = ['sans-serif', 'serif', 'monospace', 'system-ui', '-apple-system', 'blinkmacsystemfont', 'ui-sans-serif', 'ui-serif', 'ui-monospace', 'inherit', 'initial', 'arial', 'helvetica', 'helvetica neue', 'segoe ui', 'roboto', 'apple color emoji', 'segoe ui emoji', 'segoe ui symbol', 'noto color emoji', 'var', 'cursive', 'fantasy'];

    public function __construct(private PageFetcher $fetcher) {}

    /**
     * The image's most present colours, as hex, most present first. Nearly
     * transparent pixels are skipped: a logo's background is not its colour.
     *
     * @return array<int, string>
     */
    public function palette(string $image, int $count = 5): array
    {
        $source = @imagecreatefromstring($image);

        if ($source === false) {
            return [];
        }

        $small = imagescale($source, 48, 48);

        if ($small === false) {
            return [];
        }

        $buckets = [];

        for ($x = 0; $x < 48; $x++) {
            for ($y = 0; $y < 48; $y++) {
                $index = imagecolorat($small, $x, $y);

                if ($index === false) {
                    continue;
                }

                $pixel = imagecolorsforindex($small, $index);

                if ($pixel['alpha'] > 100) {
                    continue;
                }

                // Coarse buckets, so anti-aliased edges join their colour.
                $key = sprintf('#%02x%02x%02x', ($pixel['red'] >> 4) * 17, ($pixel['green'] >> 4) * 17, ($pixel['blue'] >> 4) * 17);
                $buckets[$key] = ($buckets[$key] ?? 0) + 1;
            }
        }

        arsort($buckets);

        return array_slice(array_keys($buckets), 0, $count);
    }

    private const MAX_PICTURE_BYTES = 5_000_000;

    /**
     * The logo and the sharing image the homepage at this address points
     * at, downloaded: the logo from an image named after it, else the icon
     * it gives phones; the sharing image from its `og:image`. Each null when
     * there is none, or none that is a PNG, JPEG or WebP that can be read.
     * A logo only found as an SVG comes back as its address in `vector_logo`,
     * for Chromium to draw (`SiteCapture`).
     *
     * @return array{logo: array{bytes: string, extension: string}|null, image: array{bytes: string, extension: string}|null, vector_logo: string|null}
     */
    public function pictures(string $url): array
    {
        $none = ['logo' => null, 'image' => null, 'vector_logo' => null];
        $url = Url::normalize($url) ?? $url;

        try {
            $html = (string) $this->fetcher->fetch($url)?->content;
        } catch (Throwable) {
            return $none;
        }

        if ($html === '') {
            return $none;
        }

        $dom = new DOMDocument;
        @$dom->loadHTML($html);

        $logos = [];

        foreach ($dom->getElementsByTagName('img') as $img) {
            if (str_contains(strtolower($img->getAttribute('src').' '.$img->getAttribute('alt').' '.$img->getAttribute('class').' '.$img->getAttribute('id')), 'logo')) {
                $logos[] = $img->getAttribute('src');
            }
        }

        $images = [];

        $vectors = [];

        foreach ($dom->getElementsByTagName('link') as $link) {
            $rel = strtolower($link->getAttribute('rel'));

            if (str_contains($rel, 'apple-touch-icon')) {
                $logos[] = $link->getAttribute('href');
            } elseif (str_contains($rel, 'icon') && (str_ends_with(strtolower($link->getAttribute('href')), '.svg') || $link->getAttribute('type') === 'image/svg+xml')) {
                $vectors[] = $link->getAttribute('href');
            }
        }

        foreach ($dom->getElementsByTagName('meta') as $meta) {
            if (in_array(strtolower($meta->getAttribute('property') ?: $meta->getAttribute('name')), ['og:image', 'twitter:image'], true)) {
                $images[] = $meta->getAttribute('content');
            }
        }

        $vectors = [...array_filter($logos, fn (string $src): bool => str_ends_with(strtolower(strtok($src, '?') ?: ''), '.svg')), ...$vectors];

        return [
            'logo' => $this->firstPicture($logos, $url),
            'image' => $this->firstPicture($images, $url),
            'vector_logo' => $vectors === [] ? null : Url::resolve(reset($vectors), $url),
        ];
    }

    /**
     * @param  array<int, string>  $sources
     * @return array{bytes: string, extension: string}|null
     */
    private function firstPicture(array $sources, string $base): ?array
    {
        foreach (array_unique(array_filter($sources)) as $source) {
            $url = Url::resolve($source, $base);

            if ($url === null || ! str_starts_with($url, 'http')) {
                continue;
            }

            try {
                $response = Http::withHeaders(['User-Agent' => (string) config('eveil.crawl.user_agent')])
                    ->timeout((int) config('eveil.crawl.timeout'))
                    ->get($url);
            } catch (Throwable) {
                continue;
            }

            $bytes = $response->body();
            $size = $response->successful() && strlen($bytes) <= self::MAX_PICTURE_BYTES ? @getimagesizefromstring($bytes) : false;

            // Only what the visual identity accepts, and nothing a favicon's size.
            if ($size !== false && in_array($size[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true) && $size[0] >= 64) {
                return ['bytes' => $bytes, 'extension' => image_type_to_extension($size[2], false) ?: 'png'];
            }
        }

        return null;
    }

    /**
     * The colours and the fonts the homepage at this address declares, or
     * null when it cannot be read: a hint, never a reason to fail.
     *
     * @return array{colors: array<int, string>, fonts: array<int, string>}|null
     */
    public function ofSite(string $url): ?array
    {
        try {
            // Normalised as the crawl does, so the page it cached is the one found.
            $page = $this->fetcher->fetch(Url::normalize($url) ?? $url);
        } catch (Throwable) {
            return null;
        }

        return $page?->content ? $this->site($page->content) : null;
    }

    /**
     * The colours and the fonts a homepage's HTML declares, most used first.
     *
     * @return array{colors: array<int, string>, fonts: array<int, string>}
     */
    public function site(string $html): array
    {
        $colors = [];

        if (preg_match('~<meta[^>]+name=["\']theme-color["\'][^>]+content=["\'](#[0-9a-f]{3,6})~i', $html, $theme) === 1) {
            // Declared as the brand's colour: first, whatever else is counted.
            $colors[$this->hex($theme[1])] = PHP_INT_MAX / 2;
        }

        $styles = preg_replace('~<script\b.*?</script>~is', '', $html) ?? $html;

        // Not after `&`: `&#039;` is an apostrophe, not #003399.
        preg_match_all('~(?<!&)#(?:[0-9a-f]{6}|[0-9a-f]{3})\b~i', $styles, $hexes);

        foreach ($hexes[0] as $hex) {
            $hex = $this->hex($hex);
            $colors[$hex] = ($colors[$hex] ?? 0) + 1;
        }

        arsort($colors);

        return [
            'colors' => array_slice(array_keys($colors), 0, 6),
            'fonts' => array_slice($this->fonts($styles), 0, 4),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function fonts(string $html): array
    {
        $fonts = [];

        // Google and Bunny font links: family=Space+Grotesk:wght@400 or family=inter:400,700
        preg_match_all('~fonts\.(?:googleapis\.com|bunny\.net)/css2?\?[^"\'>]*~i', $html, $links);

        foreach ($links[0] as $link) {
            preg_match_all('~family=([^:&|"\']+)~i', html_entity_decode($link), $families);

            foreach ($families[1] as $family) {
                $fonts[] = ucwords(str_replace(['+', '-'], ' ', urldecode($family)));
            }
        }

        preg_match_all('~font-family\s*:\s*([^;}"]+)~i', $html, $declarations);

        foreach ($declarations[1] as $declaration) {
            foreach (explode(',', $declaration) as $font) {
                $fonts[] = trim(html_entity_decode($font, ENT_QUOTES), " '\"\t\n\r");
            }
        }

        return array_values(array_unique(array_filter(
            $fonts,
            fn (string $font): bool => $font !== '' && ! str_starts_with($font, 'var(') && ! in_array(strtolower($font), self::GENERIC_FONTS, true),
        )));
    }

    private function hex(string $hex): string
    {
        $hex = strtolower(ltrim($hex, '#'));

        return '#'.(strlen($hex) === 3 ? $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2] : $hex);
    }
}
