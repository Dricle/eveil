<?php

namespace App\Http\Controllers;

use App\Enums\DiscoveryRunStatus;
use App\Models\DiscoveryRun;
use Illuminate\Http\RedirectResponse;

/**
 * Stopping a run is one flag, and that is the whole mechanism: everything
 * already queued reads the run's status when a worker picks it up and deletes
 * itself. Nothing to kill, no job registry to keep, and whatever was found
 * before the click stays found.
 */
class DiscoveryRunCancellationController extends Controller
{
    public function store(int $discoveryRun): RedirectResponse
    {
        $run = DiscoveryRun::query()->findOrFail($discoveryRun);

        if (! $run->status->isTerminal()) {
            $run->update([
                'status' => DiscoveryRunStatus::Aborted,
                'finished_at' => now(),
            ]);
        }

        return to_route('discovery-runs.show', $run);
    }
}
