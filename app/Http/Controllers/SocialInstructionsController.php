<?php

namespace App\Http\Controllers;

use App\Enums\SocialPlatform;
use App\Http\Requests\SocialInstructionsRequest;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;

/**
 * One network's tone box, on the AI instructions screen next to the email
 * one. Each box saves on its own: a different network's writer reads each
 * (`EveilAgent::postInstructions()`), so they never submit together.
 */
class SocialInstructionsController extends Controller
{
    public function update(SocialInstructionsRequest $request, CurrentProject $currentProject, SocialPlatform $platform): RedirectResponse
    {
        $currentProject->getOrFail()->update([$platform->instructionsColumn() => $request->validated('instructions')]);

        return to_route('settings.ai-instructions.edit');
    }
}
