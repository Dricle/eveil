<?php

namespace App\Services\Discovery\Sources;

use App\Services\Discovery\Candidate;
use App\Services\Discovery\Sources\Traits\ReportsFailures;
use App\Support\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Official government business registries (Belgium's KBO/BCE, France's
 * SIRENE, the UK's Companies House and others) behind one free proxy,
 * OpenRegistry: exhaustive and free, with none of a search engine's SEO bias.
 *
 * A registry record is a legal entity, never a website: no email, no site to
 * crawl. It is kept anyway for its registered address, the same reasoning
 * `OverpassSource` applies to a business with no `website` tag - there is
 * still an address to write to, even with nothing yet to qualify against.
 *
 * Requires a free OpenRegistry token (`OPENREGISTRY_TOKEN`): unlike Overpass
 * or SearXNG this is not a keyless service, so a self-hosted instance that
 * never set one simply does not have this source, reported like any other
 * failure rather than crashing the run.
 */
class RegistrySource implements DiscoverySourceInterface
{
    public function __construct(private Settings $settings) {}

    use ReportsFailures;

    public function name(): string
    {
        return 'registry';
    }

    /**
     * @param  array{query?: string, jurisdiction?: string}  $probe
     * @return Collection<int, Candidate>
     */
    public function search(array $probe): Collection
    {
        $query = trim((string) ($probe['query'] ?? ''));
        $jurisdiction = trim((string) ($probe['jurisdiction'] ?? ''));
        $token = (string) config('eveil.sources.registry.token');

        if ($token === '') {
            return $this->failed("{$jurisdiction}/{$query}: no OPENREGISTRY_TOKEN configured");
        }

        if ($query === '' || $jurisdiction === '') {
            return new Collection;
        }

        try {
            $response = Http::timeout((int) config('eveil.sources.registry.timeout'))
                ->withToken($token)
                ->get(rtrim((string) config('eveil.sources.registry.url'), '/').'/companies', [
                    'q' => $query,
                    'jurisdiction' => mb_strtoupper($jurisdiction),
                    'limit' => $this->settings->int('sources.registry.per_probe'),
                ]);
        } catch (Throwable $e) {
            return $this->failed("{$jurisdiction}/{$query}: {$e->getMessage()}");
        }

        if (! $response->successful()) {
            return $this->failed("{$jurisdiction}/{$query}: HTTP {$response->status()}");
        }

        /** @var array<int, array<string, mixed>> $results */
        $results = $response->json('results') ?? [];

        return (new Collection($results))
            ->map(fn (array $result): ?Candidate => $this->toCandidate($result))
            ->filter()
            ->values();
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function toCandidate(array $result): ?Candidate
    {
        $name = trim((string) ($result['company_name'] ?? ''));
        $address = trim((string) ($result['registered_address'] ?? ''));

        // With no site and no address there is nothing to qualify and
        // nothing to send: the same floor `OverpassSource` applies.
        if ($name === '' || $address === '') {
            return null;
        }

        return new Candidate(
            name: $name,
            website: null,
            source: $this->name(),
            facts: array_filter([
                'registration_number' => $result['company_id'] ?? null,
                'jurisdiction' => $result['jurisdiction'] ?? null,
                'status' => $result['status_detail'] ?? $result['status'] ?? null,
                'incorporated_at' => $result['incorporation_date'] ?? null,
                'address' => $address,
            ]),
        );
    }
}
