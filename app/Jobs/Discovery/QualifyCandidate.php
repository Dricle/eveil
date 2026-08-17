<?php

namespace App\Jobs\Discovery;

use App\Ai\Agents\CompanyQualifier;
use App\Models\DiscoveryRun;
use App\Models\DiscoveryTask;
use App\Services\Discovery\Candidate;
use App\Services\Discovery\Qualifier;
use RuntimeException;
use Throwable;

/**
 * One candidate's own site, fetched and scored against the profile. Nothing
 * here comes from a purchased database: every company kept was found, read and
 * judged.
 */
class QualifyCandidate extends DiscoveryJob
{
    protected function execute(DiscoveryRun $run, DiscoveryTask $task): array
    {
        $candidate = Candidate::fromArray($task->payload ?? []);
        $qualifier = app(Qualifier::class);
        $targetProfile = $run->targetProfile;

        if ($targetProfile === null) {
            throw new RuntimeException('The profile this run was started for has been deleted.');
        }

        if ($qualifier->alreadyKnown($targetProfile, $candidate)) {
            $this->skip("{$candidate->domain()}: already found for this project");
        }

        if ($run->qualified_count >= $run->limit('max_qualified')) {
            $this->skip("not read — this run already kept the {$run->limit('max_qualified')} companies it was asked for");
        }

        if (! $run->claim('max_pages')) {
            $this->skip("not read — this run has fetched the {$run->limit('max_pages')} pages it may fetch");
        }

        try {
            $prospect = $qualifier->qualify($targetProfile, $run, $candidate, $this->meter($task, CompanyQualifier::slug()));
        } catch (Throwable $e) {
            // Named, because a bare provider message says nothing about which
            // of two hundred candidates went wrong.
            throw new RuntimeException("{$candidate->website}: {$e->getMessage()}", previous: $e);
        }

        if ($prospect) {
            $run->claim('max_qualified');
        }

        return ['prospect' => $prospect];
    }
}
