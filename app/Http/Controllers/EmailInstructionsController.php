<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmailInstructionsRequest;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;

/**
 * The project's "How Emails are written" box - see
 * `EveilAgent::emailWritingInstructions()`. Split out from `ProjectController`
 * so it can save independently on the AI instructions settings screen,
 * alongside the separate LinkedIn box (`LinkedinInstructionsController`),
 * without submitting name/url/autonomy/lead limits along with it.
 */
class EmailInstructionsController extends Controller
{
    public function update(EmailInstructionsRequest $request, CurrentProject $currentProject): RedirectResponse
    {
        $currentProject->getOrFail()->update($request->validated());

        return to_route('settings.ai-instructions.edit');
    }
}
