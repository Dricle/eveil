<?php

namespace App\Http\Controllers;

use App\Ai\Agents\AdStrategist;
use App\Enums\AgentRunStatus;
use App\Jobs\ReviewAds;
use App\Models\AgentRun;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;

/**
 * "Review now", besides the daily schedule: right after connecting an
 * account or setting a budget, waiting until tomorrow for a first move is
 * the wrong experience.
 */
class AdReviewController extends Controller
{
    public function store(CurrentProject $currentProject): RedirectResponse
    {
        $project = $currentProject->getOrFail();

        if (! $project->adAccounts()->get()->contains->isUsable()) {
            return back()->withErrors(['review' => 'Set a monthly budget on an ad account first.']);
        }

        ReviewAds::dispatch($project, AgentRun::create([
            'project_id' => $project->id,
            'agent' => AdStrategist::slug(),
            'status' => AgentRunStatus::Pending,
        ]));

        return back()->with('status', 'The ad review is running.');
    }
}
