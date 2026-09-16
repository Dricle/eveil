<?php

namespace App\Http\Controllers;

use App\Support\CurrentProject;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Both writing-tone boxes together: emails (`EmailInstructionsController`)
 * and LinkedIn posts (`LinkedinInstructionsController`). Read-only here -
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
        ]);
    }
}
