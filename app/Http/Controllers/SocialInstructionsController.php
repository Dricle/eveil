<?php

namespace App\Http\Controllers;

use App\Http\Requests\SocialInstructionsRequest;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;

/**
 * The X and Bluesky tone box, on the AI instructions screen next to the
 * email and LinkedIn ones, saved on its own for the same reason they are:
 * a different agent reads it (`EveilAgent::socialInstructions()`).
 */
class SocialInstructionsController extends Controller
{
    public function update(SocialInstructionsRequest $request, CurrentProject $currentProject): RedirectResponse
    {
        $currentProject->getOrFail()->update($request->validated());

        return to_route('settings.ai-instructions.edit');
    }
}
