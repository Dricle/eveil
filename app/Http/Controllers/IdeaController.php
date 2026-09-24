<?php

namespace App\Http\Controllers;

use App\Enums\IdeaStatus;
use App\Jobs\GenerateArticle;
use App\Models\Idea;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;

/**
 * The two answers to an article idea the Reddit scan noted: write it now,
 * or never suggest it again. Left alone, an open idea is also what the
 * article cadence picks from. `Idea` is project-scoped, so never
 * route-model-bound (`.ai/rules/controllers.md`).
 */
class IdeaController extends Controller
{
    public function write(CurrentProject $currentProject, int $idea): RedirectResponse
    {
        $found = Idea::query()->where('status', IdeaStatus::Open)->findOrFail($idea);

        GenerateArticle::dispatch($currentProject->getOrFail(), null, $found->id);

        return back()->with('status', 'Writing that article. It will appear in the drafts in a few minutes.');
    }

    public function dismiss(int $idea): RedirectResponse
    {
        Idea::query()->findOrFail($idea)->update(['status' => IdeaStatus::Dismissed]);

        return back();
    }
}
