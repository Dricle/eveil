<?php

namespace App\Jobs\Discovery;

use App\Enums\DiscoveryTaskKind;
use App\Models\DiscoveryRun;
use App\Models\DiscoveryTask;
use App\Services\Discovery\Sources\DiscoverySourceInterface;
use App\Services\Discovery\Sources\OverpassSource;
use App\Services\Discovery\Sources\WebSearchSource;
use App\Services\Discovery\Triage;

/**
 * One probe put to one source — a map query or a web search. No model call:
 * the plan already decided where to look, and this only goes and looks.
 *
 * What comes back is sorted rather than filtered: a result is either a company
 * or a list of companies, and the lists are where businesses with no site of
 * their own are reachable at all.
 */
class RunProbe extends DiscoveryJob
{
    protected function execute(DiscoveryRun $run, DiscoveryTask $task): array
    {
        if (! $run->claim('max_queries')) {
            // Not a paywall: one run is capped so a plan that asks for eighty
            // searches cannot quietly spend eighty on somebody's own API key.
            // The plan is kept whole on the row, so raising the cap and
            // replaying this node runs exactly the search it names.
            $this->skip("not run — this search is past the {$run->limit('max_queries')} searches one run may make");
        }

        $source = $this->source((string) $task->payload['source']);
        $found = $source->search($task->payload['probe'] ?? []);

        $sorted = app(Triage::class)->sort($found, $task->project);

        foreach ($sorted['listings'] as $listing) {
            $this->fork($task, DiscoveryTaskKind::Harvest, $listing, HarvestListing::class);
        }

        $queued = $this->queueQualifications($run, $task, $sorted['candidates']);

        return [
            'found' => $found->count(),
            'candidates' => $queued,
            'listings' => count($sorted['listings']),
            // A dead source and an empty market look identical unless the run
            // says which one it met.
            'failures' => method_exists($source, 'failures') ? $source->failures() : [],
        ];
    }

    private function source(string $name): DiscoverySourceInterface
    {
        return match ($name) {
            'overpass' => app(OverpassSource::class),
            default => app(WebSearchSource::class),
        };
    }
}
