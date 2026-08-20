<?php

namespace App\Support;

use App\Enums\ContactSearchStatus;
use App\Enums\DiscoveryRunStatus;
use App\Models\Company;
use App\Models\DiscoveryRun;

/**
 * What the app is doing for this project at this moment.
 *
 * Discovery and contact searching both run in the queue for minutes, and a list
 * that shows nothing while they do reads as an empty result rather than as work
 * in progress. That is the difference between "your market is small" and "wait
 * thirty seconds", and only one of them is true.
 *
 * The counters come off the run rows rather than from a job registry: they are
 * written as the run goes, so they say how far it actually got.
 */
class ProjectActivity
{
    /**
     * @return array{
     *     searching: bool,
     *     runs: int,
     *     candidates: int,
     *     qualified: int,
     *     contact_searches: int,
     * }
     */
    public function summary(): array
    {
        $live = DiscoveryRun::query()
            ->whereNotIn('status', array_filter(
                DiscoveryRunStatus::cases(),
                fn (DiscoveryRunStatus $status): bool => $status->isTerminal(),
            ))
            ->get(['candidates_found', 'qualified_count']);

        $contactSearches = Company::query()
            ->where('contacts_status', ContactSearchStatus::Queued)
            ->count();

        return [
            'searching' => $live->isNotEmpty() || $contactSearches > 0,
            'runs' => $live->count(),
            'candidates' => (int) $live->sum('candidates_found'),
            'qualified' => (int) $live->sum('qualified_count'),
            'contact_searches' => $contactSearches,
        ];
    }
}
