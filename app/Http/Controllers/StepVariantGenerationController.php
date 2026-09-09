<?php

namespace App\Http\Controllers;

use App\Ai\Agents\VariantWriter;
use App\Enums\AgentRunStatus;
use App\Http\Requests\GenerateStepVariantRequest;
use App\Jobs\WriteStepVariant;
use App\Models\AgentRun;
use App\Models\Campaign;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;

/**
 * Asking the agent for a second wording of a step, to A/B test against the
 * first. Separate from the CRUD controller because it is a different
 * resource: a writing, which the user starts and the queue performs.
 *
 * The run row is opened here, `pending`, before the job is queued: the
 * metering middleware only writes one when the provider call begins, so
 * between the click and a worker picking the job up there would be nothing
 * to report.
 */
class StepVariantGenerationController extends Controller
{
    public function __construct(private CurrentProject $currentProject) {}

    public function store(GenerateStepVariantRequest $request, int $campaign, int $step): RedirectResponse
    {
        $project = $this->currentProject->getOrFail();
        $campaign = Campaign::query()->findOrFail($campaign);
        $step = $campaign->steps()->findOrFail($step);

        WriteStepVariant::dispatch($step, $request->validated('guidance'), AgentRun::create([
            'project_id' => $project->id,
            'agent' => VariantWriter::slug(),
            'status' => AgentRunStatus::Pending,
        ]));

        return back();
    }

    /**
     * Rewriting one existing variant in place, per the user's instruction,
     * rather than adding another one to A/B test against it.
     */
    public function regenerate(GenerateStepVariantRequest $request, int $campaign, int $step, int $variant): RedirectResponse
    {
        $project = $this->currentProject->getOrFail();
        $campaign = Campaign::query()->findOrFail($campaign);
        $step = $campaign->steps()->findOrFail($step);
        $variant = $step->variants()->findOrFail($variant);

        WriteStepVariant::dispatch($step, $request->validated('guidance'), AgentRun::create([
            'project_id' => $project->id,
            'agent' => VariantWriter::slug(),
            'status' => AgentRunStatus::Pending,
        ]), $variant);

        return back();
    }
}
