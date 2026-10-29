<?php

namespace App\Services\Video;

/**
 * What a brand looks like, read from its own material, so its videos wear
 * its colours and type rather than a house style: the logo's dominant
 * colours, and the colours and fonts its homepage declares.
 *
 * Both are hints for `VideoDirector`, which also sees the logo itself: the
 * homepage's stylesheets are not fetched, so only what its HTML carries
 * (a theme colour, inline and embedded styles, utility classes with a
 * colour in them, a web font link) is read.
 */
class BrandStyle
{
    private const GENERIC_FONTS = ['sans-serif', 'serif', 'monospace', 'system-ui', '-apple-system', 'blinkmacsystemfont', 'ui-sans-serif', 'ui-serif', 'ui-monospace', 'inherit', 'initial', 'arial', 'helvetica', 'helvetica neue', 'segoe ui', 'roboto', 'apple color emoji', 'segoe ui emoji', 'segoe ui symbol', 'noto color emoji', 'var', 'cursive', 'fantasy'];

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

        preg_match_all('~#(?:[0-9a-f]{6}|[0-9a-f]{3})\b~i', $styles, $hexes);

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
