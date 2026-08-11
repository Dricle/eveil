<?php

namespace App\Discovery\Sources;

use App\Discovery\Candidate;
use App\Discovery\Url;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * OpenStreetMap via Overpass (ADR-006): free, no key, and by far the best way
 * to enumerate local businesses — the long tail a purchased contact database
 * simply does not carry.
 *
 * Only entries with a website survive: without a domain there is nothing to
 * qualify and no email to infer.
 */
class OverpassSource implements DiscoverySource
{
    use ReportsFailures;

    public function name(): string
    {
        return 'overpass';
    }

    /**
     * @param  array{area?: string, country?: string, tags?: array<string, string>}  $probe
     * @return Collection<int, Candidate>
     */
    public function search(array $probe): Collection
    {
        $area = trim((string) ($probe['area'] ?? ''));
        $tags = $probe['tags'] ?? [];

        if ($area === '' || $tags === []) {
            return new Collection;
        }

        try {
            $response = Http::timeout((int) config('eveil.sources.overpass.timeout'))
                // Overpass answers 406 to Guzzle's default User-Agent and asks
                // that clients identify themselves. Without this the source
                // returns nothing, forever, silently.
                ->withHeaders([
                    'User-Agent' => (string) config('eveil.crawl.user_agent'),
                    'Accept' => 'application/json',
                ])
                ->asForm()
                ->post((string) config('eveil.sources.overpass.url'), [
                    'data' => $this->query($area, $tags, (string) ($probe['country'] ?? '')),
                ]);
        } catch (Throwable $e) {
            return $this->failed("{$area}: {$e->getMessage()}");
        }

        if (! $response->successful()) {
            return $this->failed("{$area}: HTTP {$response->status()}");
        }

        /** @var array<int, array<string, mixed>> $elements */
        $elements = $response->json('elements') ?? [];

        return (new Collection($elements))
            ->map(fn (array $element): ?Candidate => $this->toCandidate($element, $area))
            ->filter()
            ->unique(fn (Candidate $candidate): string => $candidate->domain() ?? $candidate->website)
            ->values();
    }

    /**
     * Place names are not unique: a probe on "Charleroi" without a country also
     * returns Charleroi, Pennsylvania. The country is resolved first and the
     * town looked up inside it.
     *
     * @param  array<string, string>  $tags
     */
    private function query(string $area, array $tags, string $country): string
    {
        $filters = collect($tags)
            ->map(fn (string $value, string $key): string => '["'.addslashes($key).'"="'.addslashes($value).'"]')
            ->implode('');

        $limit = (int) config('eveil.sources.overpass.per_probe');
        $area = addslashes($area);

        $scope = $country === ''
            ? "area[\"name\"=\"{$area}\"]->.searchArea;"
            : 'area["ISO3166-1"="'.addslashes(mb_strtoupper($country)).'"]["admin_level"="2"]->.country;'
                ."\nrel[\"name\"=\"{$area}\"][\"boundary\"=\"administrative\"](area.country);\nmap_to_area->.searchArea;";

        return <<<QL
        [out:json][timeout:50];
        {$scope}
        nwr{$filters}["website"](area.searchArea);
        out center tags {$limit};
        QL;
    }

    /**
     * @param  array<string, mixed>  $element
     */
    private function toCandidate(array $element, string $area): ?Candidate
    {
        /** @var array<string, string> $tags */
        $tags = $element['tags'] ?? [];

        $website = $tags['website'] ?? $tags['contact:website'] ?? null;
        $normalized = is_string($website) ? Url::normalize($this->withScheme($website)) : null;

        if ($normalized === null || ($tags['name'] ?? '') === '') {
            return null;
        }

        return new Candidate(
            name: $tags['name'],
            website: $normalized,
            source: $this->name(),
            sourceUrl: 'https://www.openstreetmap.org/'.($element['type'] ?? 'node').'/'.($element['id'] ?? ''),
            facts: array_filter([
                'area' => $area,
                'phone' => $tags['phone'] ?? $tags['contact:phone'] ?? null,
                'street' => trim(($tags['addr:street'] ?? '').' '.($tags['addr:housenumber'] ?? '')) ?: null,
                'city' => $tags['addr:city'] ?? null,
                'cuisine' => $tags['cuisine'] ?? null,
                'amenity' => $tags['amenity'] ?? $tags['shop'] ?? null,
            ]),
        );
    }

    /**
     * OSM contributors write `example.be` as often as a full URL.
     */
    private function withScheme(string $website): string
    {
        return str_starts_with($website, 'http') ? $website : 'https://'.ltrim($website, '/');
    }
}
