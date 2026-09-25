<?php

namespace App\Http\Controllers;

use App\Support\CurrentProject;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every writing-tone box together: emails (`EmailInstructionsController`),
 * LinkedIn posts (`LinkedinInstructionsController`) and X/Bluesky posts
 * (`SocialInstructionsController`). Read-only here -
 * each box saves through its own small controller, same reasoning as
 * splitting them in `EveilAgent` (`.ai/rules/ai.md`): two different agents
 * read them, so two different forms write them.
 */
class AiInstructionsController extends Controller
{
    public function edit(CurrentProject $currentProject): Response
    {
        $project = $currentProject->getOrFail();

        return Inertia::render('settings/AiInstructions', [
            'promptInstructions' => $project->prompt_instructions,
            'linkedinPromptInstructions' => $project->linkedin_prompt_instructions,
            'socialPromptInstructions' => $project->social_prompt_instructions,
        ]);
    }
}
