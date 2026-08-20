<?php

namespace App\Services\Discovery;

use App\Ai\Agents\ResultTriage;
use App\Enums\HostKind;
use App\Models\KnownHost;
use App\Models\Project;
use App\Support\Settings;
use App\Support\Url;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Decides what each host in a batch of search results IS, and remembers it.
 *
 * A hand-written blocklist of aggregators cannot be completed: Pages d'Or,
 * Product Hunt, BetaList, Clutch, every trade directory in every country, and
 * it was deleting the very results worth the most, since a directory page is
 * hundreds of businesses rather than one. So the model judges, once per host
 * ever, and `known_hosts` keeps the answer for every future run of every
 * project on the instance.
 *
 * Two layers: the registry, then the model for whatever it does not know. The
 * certainties: search engines, encyclopaedias, the social platforms. Used to
 * be a third layer, a const in this class, until it became obvious that a
 * hardcoded list shadowing a table IS the table, minus the ability to edit it.
 * They are locked rows now, seeded by `KnownHostSeeder`, and a superadmin can
 * change one.
 */
class HostRegistry
{
    public function __construct(private Settings $settings) {}

    /**
     * @param  Collection<int, string>  $urls
     * @return array<string, HostKind> host => kind, every host in the batch
     */
    public function classify(Collection $urls, Project $project): array
    {
        $counts = $urls
            ->map(fn (string $url): ?string => Url::host($url))
            ->filter()
            ->countBy()
            ->all();

        $hosts = array_map(strval(...), array_keys($counts));
        $known = $this->lookup($hosts);

        $verdicts = [];
        $unknown = [];

        foreach ($hosts as $host) {
            $row = $known[$host] ?? null;

            if ($row !== null && $row->isAuthoritative()) {
                $verdicts[$host] = $row->kind;

                continue;
            }

            $unknown[$host] = $counts[$host];
        }

        $batch = max(1, $this->settings->int('sources.host_registry.batch'));

        foreach (array_chunk($unknown, $batch, true) as $chunk) {
            foreach ($this->ask($chunk, $urls, $project) as $host => $kind) {
                $verdicts[$host] = $kind;
            }
        }

        return $verdicts;
    }

    /**
     * Records what a harvest actually did, so a host behind bot protection is
     * never paid for twice and a productive one can be gone back to directly.
     */
    public function recordHarvest(string $host, Harvest $harvest): void
    {
        $known = KnownHost::query()->firstWhere('host', $host);

        if ($known === null || $known->is_locked) {
            return;
        }

        $known->forceFill([
            'harvest_status' => $harvest->status(),
            'pages_harvested' => $known->pages_harvested + count($harvest->pages),
            'businesses_found' => $known->businesses_found + $harvest->candidates->count(),
            'last_harvested_at' => now(),
        ])->save();
    }

    /**
     * Resolves each host against the registry, falling back to its parent
     * domain: `fr.wikipedia.org` answers from the `wikipedia.org` row, and
     * `nl.pagesdor.be` from `pagesdor.be`, so a directory does not have to be
     * judged once per language subdomain.
     *
     * Stops at two labels, which is wrong for `co.uk`-style suffixes but only
     * matters if somebody creates a row for one, and the alternative is a
     * public-suffix dependency for a case that has not come up.
     *
     * One query for the whole batch. The old floor answered without touching
     * the database at all; the cost of losing that is a single `whereIn` per
     * search, against the several individual lookups it replaces.
     *
     * @param  array<int, string>  $hosts
     * @return array<string, KnownHost>
     */
    private function lookup(array $hosts): array
    {
        $chains = [];

        foreach ($hosts as $host) {
            foreach ($this->parents($host) as $candidate) {
                $chains[$host][] = $candidate;
            }
        }

        $rows = KnownHost::query()
            ->whereIn('host', collect($chains)->flatten()->unique()->all())
            ->get()
            ->keyBy('host');

        $resolved = [];

        foreach ($chains as $host => $candidates) {
            foreach ($candidates as $candidate) {
                if ($rows->has($candidate)) {
                    $resolved[$host] = $rows->get($candidate);

                    break;
                }
            }
        }

        return $resolved;
    }

    /**
     * The host itself, then each parent domain down to two labels.
     *
     * @return array<int, string>
     */
    private function parents(string $host): array
    {
        $labels = explode('.', $host);
        $chain = [];

        while (count($labels) >= 2) {
            $chain[] = implode('.', $labels);
            array_shift($labels);
        }

        return $chain;
    }

    /**
     * @param  array<string, int>  $hosts  host => how many results sit on it
     * @param  Collection<int, string>  $urls
     * @return array<string, HostKind>
     */
    private function ask(array $hosts, Collection $urls, Project $project): array
    {
        try {
            $response = (new ResultTriage($project))->prompt($this->prompt($hosts, $urls));
        } catch (Throwable) {
            // A triage that fails must not fail the run: treat the batch as
            // ordinary company sites, which is what happened before any of this
            // existed. Nothing is written, so the next run tries again.
            return array_fill_keys(array_keys($hosts), HostKind::Entity);
        }

        /** @var array<int, array{host?: string, kind?: string, reason?: string}> $judged */
        $judged = $response->structured['hosts'] ?? [];
        $verdicts = [];

        foreach ($judged as $verdict) {
            $host = trim($verdict['host'] ?? '');
            $kind = HostKind::tryFrom($verdict['kind'] ?? '');

            if ($host === '' || $kind === null || ! array_key_exists($host, $hosts)) {
                continue;
            }

            $verdicts[$host] = $kind;

            KnownHost::query()->updateOrCreate(
                ['host' => $host],
                ['kind' => $kind, 'reason' => $verdict['reason'] ?? null, 'last_verified_at' => now()],
            );
        }

        // A host the model skipped is left unrecorded on purpose: better to
        // ask again next time than to cache a guess we did not make.
        return $verdicts + array_fill_keys(array_keys($hosts), HostKind::Entity);
    }

    /**
     * One line per host: the count is the strongest signal that something is an
     * index, and it costs nothing to compute.
     *
     * @param  array<string, int>  $hosts
     * @param  Collection<int, string>  $urls
     */
    private function prompt(array $hosts, Collection $urls): string
    {
        $lines = [];

        foreach ($hosts as $host => $count) {
            $sample = $urls->first(fn (string $url): bool => Url::host($url) === $host) ?? $host;

            $lines[] = "{$host}: {$count} of {$urls->count()} results. E.g. {$sample}";
        }

        return "Search results by host:\n\n".implode("\n", $lines);
    }
}
