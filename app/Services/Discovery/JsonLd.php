<?php

namespace App\Services\Discovery;

use App\Support\HtmlText;
use App\Support\Url;
use DOMElement;
use DOMXPath;

/**
 * Pulls businesses out of a page's `application/ld+json` blocks.
 *
 * This is the load-bearing rung of the harvest ladder: directories emit
 * structured data because SEO is their entire business, so ONE parser covers
 * most of them and the LLM extractor stays a fallback. A page that ships
 * JSON-LD costs nothing to read.
 */
class JsonLd
{
    /**
     * Node types that carry a name but are never a company. Matching on the
     * denylist rather than enumerating schema.org's hundred-odd LocalBusiness
     * subtypes: a directory for veterinarians uses `VeterinaryCare`, one for
     * bakeries uses `Bakery`, and no allowlist survives that.
     */
    private const NOT_A_BUSINESS = [
        'person', 'webpage', 'website', 'breadcrumblist', 'searchaction', 'listitem',
        'article', 'blogposting', 'newsarticle', 'imageobject', 'videoobject', 'offer',
        'aggregaterating', 'review', 'rating', 'postaladdress', 'geocoordinates',
        'openinghoursspecification', 'contactpoint', 'sitenavigationelement', 'collectionpage',
    ];

    /**
     * @return array<int, array{name: string, url: ?string, email: ?string, phone: ?string, address: ?string}>
     */
    public static function businesses(string $html, string $baseUrl): array
    {
        $found = [];

        foreach (self::blocks($html) as $block) {
            self::walk($block, $found);
        }

        return collect($found)
            ->map(fn (array $node): ?array => self::toBusiness($node, $baseUrl))
            ->filter()
            ->unique(fn (array $business): string => mb_strtolower($business['name']).'|'.($business['url'] ?? ''))
            ->values()
            ->all();
    }

    /**
     * @return array<int, mixed>
     */
    private static function blocks(string $html): array
    {
        $document = (new HtmlText)->document($html);

        if ($document === null) {
            return [];
        }

        $blocks = [];
        $xpath = new DOMXPath($document);
        $nodes = $xpath->query('//script[@type="application/ld+json"]');

        foreach ($nodes === false ? [] : $nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            // A single malformed block is normal on real pages and must never
            // cost us the well-formed ones next to it.
            $decoded = json_decode(trim($node->textContent), true);

            if ($decoded !== null) {
                $blocks[] = $decoded;
            }
        }

        return $blocks;
    }

    /**
     * Descends through `@graph`, `itemListElement` and plain arrays, collecting
     * every object that looks like an entity. Directories nest their listings
     * differently and there is no point guessing which shape a given one uses.
     *
     * @param  array<int, array<mixed>>  $found
     */
    private static function walk(mixed $node, array &$found, int $depth = 0): void
    {
        if ($depth > 6 || ! is_array($node)) {
            return;
        }

        if (array_is_list($node)) {
            foreach ($node as $child) {
                self::walk($child, $found, $depth + 1);
            }

            return;
        }

        if (isset($node['name']) && is_string($node['name'])) {
            $found[] = $node;
        }

        foreach (['@graph', 'itemListElement', 'item', 'mainEntity', 'about'] as $key) {
            if (isset($node[$key])) {
                self::walk($node[$key], $found, $depth + 1);
            }
        }
    }

    /**
     * @param  array<mixed>  $node
     * @return array{name: string, url: ?string, email: ?string, phone: ?string, address: ?string}|null
     */
    private static function toBusiness(array $node, string $baseUrl): ?array
    {
        $name = is_string($node['name'] ?? null) ? trim($node['name']) : '';

        if ($name === '' || self::isExcludedType($node)) {
            return null;
        }

        $url = self::url($node, $baseUrl);
        $email = self::string($node, 'email');
        $phone = self::string($node, 'telephone') ?? self::string($node, 'faxNumber');
        $address = self::address($node['address'] ?? null);

        // A name on its own is a heading, a breadcrumb or a logo caption. One
        // contact detail is what makes it an entity worth keeping.
        if ($url === null && $email === null && $phone === null && $address === null) {
            return null;
        }

        return [
            'name' => $name,
            'url' => $url,
            'email' => $email === null ? null : mb_strtolower(str_replace('mailto:', '', $email)),
            'phone' => $phone,
            'address' => $address,
        ];
    }

    /**
     * @param  array<mixed>  $node
     */
    private static function isExcludedType(array $node): bool
    {
        $types = $node['@type'] ?? $node['type'] ?? null;
        $types = is_array($types) ? $types : [$types];

        foreach ($types as $type) {
            if (is_string($type) && in_array(mb_strtolower($type), self::NOT_A_BUSINESS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<mixed>  $node
     */
    private static function url(array $node, string $baseUrl): ?string
    {
        $url = $node['url'] ?? $node['sameAs'] ?? null;
        $url = is_array($url) ? ($url[0] ?? null) : $url;

        return is_string($url) ? Url::resolve($url, $baseUrl) : null;
    }

    /**
     * @param  array<mixed>  $node
     */
    private static function string(array $node, string $key): ?string
    {
        $value = $node[$key] ?? null;
        $value = is_array($value) ? ($value[0] ?? null) : $value;
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }

    private static function address(mixed $address): ?string
    {
        if (is_string($address)) {
            return trim($address) === '' ? null : trim($address);
        }

        if (! is_array($address)) {
            return null;
        }

        $parts = collect(['streetAddress', 'postalCode', 'addressLocality', 'addressCountry'])
            ->map(fn (string $key): string => is_string($address[$key] ?? null) ? trim($address[$key]) : '')
            ->filter()
            ->all();

        return $parts === [] ? null : implode(', ', $parts);
    }
}
