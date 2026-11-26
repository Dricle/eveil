<?php

namespace App\Http\Controllers;

use App\Ai\Agents\LaunchCopywriter;
use App\Enums\AgentRunStatus;
use App\Jobs\DraftLaunchCopy;
use App\Models\AgentRun;
use App\Models\LaunchItem;
use Illuminate\Http\RedirectResponse;

/**
 * "Write the copy" on one checklist row: queues the copywriter, which
 * replaces the row's draft when it answers.
 */
class LaunchDraftController extends Controller
{
    public function store(int $launchItem): RedirectResponse
    {
        $item = LaunchItem::query()->findOrFail($launchItem);

        if ($item->agentRun?->isInFlight()) {
            return back();
        }

        $run = AgentRun::create([
            'project_id' => $item->project_id,
            'agent' => LaunchCopywriter::slug(),
            'status' => AgentRunStatus::Pending,
        ]);

        $item->update(['agent_run_id' => $run->id]);
        DraftLaunchCopy::dispatch($item, $run);

        return back();
    }
}
