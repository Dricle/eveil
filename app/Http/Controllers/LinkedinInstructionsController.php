<?php

namespace App\Http\Controllers;

use App\Http\Requests\LinkedinInstructionsRequest;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;

/**
 * The project's own tone for LinkedIn specifically, on top of the general
 * "how the AI writes" box (`ProjectController`) - see
 * `EveilAgent::linkedinInstructions()`. Lives on the posts queue screen, same
 * reasoning as `LinkedinCadenceController`: it is a decision about this
 * queue's own voice, not the project's settings in general.
 */
class LinkedinInstructionsController extends Controller
{
    public function update(LinkedinInstructionsRequest $request, CurrentProject $currentProject): RedirectResponse
    {
        $currentProject->getOrFail()->update($request->validated());

        return to_route('linkedin.posts.index');
    }
}
