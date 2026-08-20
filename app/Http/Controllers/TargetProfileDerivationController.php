<?php

namespace App\Http\Controllers;

use App\Ai\Agents\TargetProfileDeriver;
use App\Enums\AgentRunStatus;
use App\Jobs\DeriveTargets;
use App\Models\AgentRun;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Asking the agent to work the profiles out again from the product portrait.
 * Separate from the CRUD controller because it is a different resource: a
 * derivation, which the user starts and the queue performs.
 *
 * The run row is opened here, `pending`, before the job is queued: the metering
 * middleware only writes one when the provider call begins, so between the
 * click and a worker picking the job up there would otherwise be nothing at all
 * to report, and the screen would look like the button did nothing.
 *
 * `replace` is the caller's choice and defaults to NO. Re-deriving is the
 * ordinary reason to come here: the product has changed, the profiles should
 * change with it, but throwing away what is already on screen is destructive,
 * so it has to be asked for rather than assumed. A profile the user wrote or
 * corrected survives either way; the agent only discards its own work.
 */
class TargetProfileDerivationController extends Controller
{
    public function __construct(private CurrentProject $currentProject) {}

    public function store(Request $request): RedirectResponse
    {
        $project = $this->currentProject->getOrFail();

        DeriveTargets::dispatch($project, AgentRun::create([
            'project_id' => $project->id,
            'agent' => TargetProfileDeriver::slug(),
            'status' => AgentRunStatus::Pending,
        ]), $request->boolean('replace'));

        return back();
    }
}
