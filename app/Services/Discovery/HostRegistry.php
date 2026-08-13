<?php

namespace App\Services\Discovery;

use App\Ai\Agents\ResultTriage;
use App\Enums\HostKind;
use App\Models\KnownHost;
use App\Models\Project;
use App\Support\Url;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Decides what each host in a batch of search results IS, and remembers it.
 *
 * A hand-written blocklist of aggregators cannot be completed — Pages d'Or,
 * Product Hunt, BetaList, Clutch, every trade directory in every country — and
 * it was deleting the very results worth the most, since a directory page is
 * hundreds of businesses rather than one. So the model judges, once per host
 * ever, and `known_hosts` keeps the answer for every future run of every
 * project on the instance.
 *
 * Three layers, cheapest first: the floor below, then the registry, then the
 * model for whatever is left.
 */
class HostRegistry
{
    /**
     * The only hosts decided without asking. NOT an attempt to list aggregators
     * — that is the job the model took over — and NOT a list of things nobody
     * wants: a verdict here is STRUCTURAL and holds for every possible target
     * profile.
     *
     * That distinction is easy to get wrong and was, at first. Job boards,
     * marketplaces, delivery platforms and code hosting all looked like noise
     * until you notice a recruitment agency hunts companies that are hiring,
     * and a developer-tool ICP lives on code hosting. Those are indexes for
     * everyone; whether their contents fit a given profile is qualification's
     * problem, not this table's. What is left here is genuinely never either a
     * company or a list of companies.
     */
    private const FLOOR = [
        // Structurally lists of organisations, but automated access is blocked
        // and their terms forbid it, so the kind is moot.
        HostKind::Social->value => [
            'facebook.com', 'instagram.com', 'linkedin.com', 'x.com', 'twitter.com',
            'tiktok.com', 'pinterest.', 'youtube.com', 'snapchat.com', 'threads.net',
        ],
        HostKind::Other->value => [
            'google.', 'bing.com', 'duckduckgo.com', 'search.brave.com', 'ecosia.org',
            'wikipedia.org', 'wikimedia.org', 'archive.org',
            'reddit.com', 'quora.com', 'stackoverflow.com',
        ],
    ];

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

        $verdicts = [];
        $unknown = [];

        foreach (array_keys($counts) as $host) {
            $host = (string) $host;
            $floor = $this->floorKind($host);

            if ($floor !== null) {
                $verdicts[$host] = $floor;

                continue;
            }

            $known = KnownHost::query()->where('host', $host)->first();

            if ($known !== null && $known->isAuthoritative()) {
                $verdicts[$host] = $known->kind;

                continue;
            }

            $unknown[$host] = $counts[$host];
        }

        $batch = max(1, (int) config('eveil.sources.host_registry.batch'));

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

    private function floorKind(string $host): ?HostKind
    {
        foreach (self::FLOOR as $kind => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($host, $needle)) {
                    return HostKind::from($kind);
                }
            }
        }

        return null;
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

        // A host the model skipped is left unrecorded on purpose — better to
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

            $lines[] = "{$host} — {$count} of {$urls->count()} results — e.g. {$sample}";
        }

        return "Search results by host:\n\n".implode("\n", $lines);
    }
}
