<?php

namespace App\Services\Discovery;

use App\Enums\HostKind;
use App\Models\Project;
use App\Support\Url;
use Illuminate\Support\Collection;

/**
 * Turns one source's raw results into work: the companies worth qualifying, and
 * the directories worth reading.
 *
 * A result is either a company or a LIST of companies, and telling them apart
 * used to be a hand-written blocklist of aggregator domains — which could never
 * be complete, and which threw away the most valuable results of all. A
 * directory's page for one trade in one town is not a company, it is hundreds,
 * and for a business with no site of its own it is the only place an address is
 * published.
 */
class Triage
{
    public function __construct(private HostRegistry $hosts) {}

    /**
     * @param  Collection<int, Candidate>  $found
     * @return array{candidates: Collection<int, Candidate>, listings: array<int, array{host: string, url: string}>}
     */
    public function sort(Collection $found, Project $project): array
    {
        /** @var Collection<int, Candidate> $candidates */
        $candidates = new Collection;
        $listings = [];

        if ($found->isEmpty()) {
            return ['candidates' => $candidates, 'listings' => $listings];
        }

        $kinds = $this->hosts->classify(
            $found->map(fn (Candidate $candidate): string => (string) ($candidate->sourceUrl ?? $candidate->website)),
            $project,
        );

        $seen = [];

        foreach ($found as $candidate) {
            $url = (string) ($candidate->sourceUrl ?? $candidate->website);
            $host = Url::host($url);
            $kind = $kinds[$host] ?? HostKind::Entity;

            if ($kind === HostKind::Entity) {
                $candidates->push($candidate);

                continue;
            }

            // Social and `other` are dropped — `other` because we read hosts
            // and not pages, so a forum thread that names ten businesses goes
            // with them. A limit worth revisiting, not a claim that the page
            // was worthless.
            if ($kind !== HostKind::Index || $host === null || isset($seen[$host])) {
                continue;
            }

            $seen[$host] = true;

            // A directory is ALSO a company, and somebody's target profile is
            // "launch platforms" or "review sites". Harvesting and qualifying
            // are not alternatives: the host goes in as a candidate too, and
            // qualification decides what it is worth. For a restaurant profile
            // that costs one page and scores near zero; leaving it out would
            // silently make a whole category of buyer unserviceable.
            $candidates->push(new Candidate(
                name: $host,
                website: 'https://'.$host,
                source: $candidate->source,
                sourceUrl: $url,
                facts: ['host_kind' => $kind->value],
            ));

            $listings[] = ['host' => $host, 'url' => $url];
        }

        return ['candidates' => $candidates, 'listings' => $listings];
    }
}
