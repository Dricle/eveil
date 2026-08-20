<?php

namespace App\Jobs\Discovery;

use App\Models\DiscoveryRun;
use App\Models\DiscoveryTask;
use App\Models\KnownHost;
use App\Services\Discovery\HostRegistry;
use App\Services\Discovery\ListingHarvester;

/**
 * One directory page read for the businesses on it. A page of listings is not
 * one company, it is hundreds, and for a business that publishes no site of
 * its own, it is the only place an address appears at all.
 *
 * Its own node because it is multi-page and budgeted: a directory that fights
 * back must cost its own row, not the probe that found it.
 */
class HarvestListing extends DiscoveryJob
{
    protected function execute(DiscoveryRun $run, DiscoveryTask $task): array
    {
        $host = (string) $task->payload['host'];
        $url = (string) $task->payload['url'];

        $known = KnownHost::query()->firstWhere('host', $host);

        if ($known !== null && ! $known->isWorthHarvesting()) {
            $this->skip("{$host}: skipped, {$known->harvest_status?->value} last time");
        }

        $harvest = app(ListingHarvester::class)->harvest($url, $task->project, $run->budget['max_pages'] ?? null);

        app(HostRegistry::class)->recordHarvest($host, $harvest);

        return [
            'harvested' => $harvest->candidates->count(),
            'candidates' => $this->queueQualifications($run, $task, $harvest->candidates),
            'without_website' => $harvest->withoutWebsite(),
            'pages' => count($harvest->pages),
            'status' => $harvest->status()->value,
        ];
    }
}
