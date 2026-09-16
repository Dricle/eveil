<?php

namespace App\Http\Controllers;

use App\Http\Requests\LinkedinInstructionsRequest;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;

/**
 * The project's own tone for LinkedIn specifically, independent of the
 * "How Emails are written" box - see `EveilAgent::linkedinInstructions()`.
 * Lives on the AI instructions settings screen next to that one
 * (`AiInstructionsController`), not the posts queue: it is a writing-tone
 * setting, same category as the email box, not the queue's own rhythm
 * (`LinkedinCadenceController`, which does stay on the queue).
 */
class LinkedinInstructionsController extends Controller
{
    public function update(LinkedinInstructionsRequest $request, CurrentProject $currentProject): RedirectResponse
    {
        $currentProject->getOrFail()->update($request->validated());

        return to_route('settings.ai-instructions.edit');
    }
}
