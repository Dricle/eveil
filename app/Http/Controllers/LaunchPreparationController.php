<?php

namespace App\Http\Controllers;

use App\Ai\Agents\LaunchPlanner;
use App\Enums\AgentRunStatus;
use App\Jobs\PrepareLaunch;
use App\Models\AgentRun;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;

/**
 * "Prepare my launch", and "Look for new venues" once it has run: queues
 * the planner, which only ever adds to the checklist.
 */
class LaunchPreparationController extends Controller
{
    public function store(CurrentProject $currentProject): RedirectResponse
    {
        $project = $currentProject->getOrFail();

        PrepareLaunch::dispatch($project, AgentRun::create([
            'project_id' => $project->id,
            'agent' => LaunchPlanner::slug(),
            'status' => AgentRunStatus::Pending,
        ]));

        return back();
    }
}
