<?php

namespace App\Http\Controllers;

use App\Enums\DiscoveryRunStatus;
use App\Enums\DiscoveryTaskStatus;
use App\Models\DiscoveryTask;
use Illuminate\Http\RedirectResponse;

/**
 * Running one node again — the row already holds everything the job needs, so a
 * replay is a dispatch and nothing else. A directory that was down, a provider
 * that timed out, a page that has changed: none of them are worth paying for
 * the whole run a second time.
 */
class DiscoveryTaskReplayController extends Controller
{
    public function store(int $discoveryTask): RedirectResponse
    {
        $task = DiscoveryTask::query()->findOrFail($discoveryTask);
        $run = $task->discoveryRun;

        // A finished run has to be opened again, or the node would read the
        // stop flag and delete itself on the way in. It closes itself once the
        // replay is the last thing left.
        if ($run->status->isTerminal()) {
            $run->update([
                'status' => DiscoveryRunStatus::Running,
                'diagnosis' => null,
                'error' => null,
                'finished_at' => null,
            ]);
        }

        $task->update(['status' => DiscoveryTaskStatus::Pending]);

        $task->kind->job()::dispatch($task);

        return to_route('discovery-runs.show', $run);
    }
}
