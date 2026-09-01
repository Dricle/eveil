<?php

namespace App\Jobs\Discovery;

use App\Enums\DiscoveryTaskKind;
use App\Enums\HostKind;
use App\Models\DiscoveryRun;
use App\Models\DiscoveryTask;
use App\Services\Discovery\Candidate;
use App\Services\Discovery\HostRegistry;
use App\Support\Url;
use RuntimeException;

/**
 * One URL a user already had, sorted the same way a search result is: a
 * company's own site goes straight to qualification, a directory goes to the
 * harvester, and anything else (a social platform, an article, an aggregator
 * homepage) is not silently dropped, it fails with a reason naming this
 * specific link.
 *
 * No plan and no probe precede this node: the user already said where to
 * look, so the run goes straight here.
 */
class ClassifyLink extends DiscoveryJob
{
    protected function execute(DiscoveryRun $run, DiscoveryTask $task): array
    {
        $url = (string) $task->payload['url'];
        $host = Url::host($url);

        if ($host === null) {
            throw new RuntimeException("{$url}: not a web address that could be read.");
        }

        $kind = app(HostRegistry::class)->classify(collect([$url]), $task->project)[$host] ?? HostKind::Entity;

        if ($kind === HostKind::Entity) {
            return [
                'kind' => $kind->value,
                'candidates' => $this->queueQualifications($run, $task, [new Candidate(
                    name: $host,
                    website: $url,
                    source: 'user-submitted',
                    sourceUrl: $url,
                )]),
            ];
        }

        if ($kind === HostKind::Index) {
            // A directory is also a company (ADR-033): harvesting its listings
            // and qualifying the host itself are not alternatives, or a target
            // profile of "launch platforms" or "review sites" would never be
            // servicable through this entry point either.
            $queued = $this->queueQualifications($run, $task, [new Candidate(
                name: $host,
                website: 'https://'.$host,
                source: 'user-submitted',
                sourceUrl: $url,
            )]);

            $this->fork($task, DiscoveryTaskKind::Harvest, [
                'host' => $host,
                'url' => $url,
                'source' => 'user-submitted',
            ], HarvestListing::class);

            return ['kind' => $kind->value, 'candidates' => $queued, 'listings' => 1];
        }

        // A social platform, a search engine, an article: not silently
        // dropped. The user pointed at this link on purpose, so it fails with
        // the reason attached rather than vanishing into a count.
        throw new RuntimeException("{$host}: classified as {$kind->value}, not a company site or a directory.");
    }
}
