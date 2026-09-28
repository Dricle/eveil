<?php

namespace App\Http\Controllers;

use App\Enums\SocialPlatform;
use App\Support\CurrentProject;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every writing-tone box together: emails (`EmailInstructionsController`)
 * and one per social network (`SocialInstructionsController`). Read-only
 * here - each box saves through its own route, same reasoning as splitting
 * them in `EveilAgent` (`.ai/rules/ai.md`): a different agent, or a
 * different network, reads each one.
 */
class AiInstructionsController extends Controller
{
    public function edit(CurrentProject $currentProject): Response
    {
        $project = $currentProject->getOrFail();

        return Inertia::render('settings/AiInstructions', [
            'promptInstructions' => $project->prompt_instructions,
            'postInstructions' => collect(SocialPlatform::cases())
                ->mapWithKeys(fn (SocialPlatform $platform): array => [$platform->value => $project->getAttribute($platform->instructionsColumn())]),
        ]);
    }
}
