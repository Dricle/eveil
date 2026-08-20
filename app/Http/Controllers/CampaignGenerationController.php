<?php

namespace App\Http\Controllers;

use App\Ai\Agents\SequenceWriter;
use App\Enums\AgentRunStatus;
use App\Jobs\WriteCampaign;
use App\Models\AgentRun;
use App\Models\TargetProfile;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Asking the agent to write a sequence for one segment. Separate from the CRUD
 * controller because it is a different resource: a writing, which the user
 * starts and the queue performs.
 *
 * The run row is opened here, `pending`, before the job is queued: the metering
 * middleware only writes one when the provider call begins, so between the
 * click and a worker picking the job up there would be nothing to report.
 *
 * No validation rule on the profile id: the global scope already answers
 * whether it belongs to this project, and anything else is a 404.
 */
class CampaignGenerationController extends Controller
{
    public function __construct(private CurrentProject $currentProject) {}

    public function store(Request $request): RedirectResponse
    {
        $project = $this->currentProject->getOrFail();
        $targetProfile = TargetProfile::query()->findOrFail($request->integer('target_profile'));

        WriteCampaign::dispatch($project, $targetProfile, AgentRun::create([
            'project_id' => $project->id,
            'agent' => SequenceWriter::slug(),
            'status' => AgentRunStatus::Pending,
        ]));

        return back();
    }
}
