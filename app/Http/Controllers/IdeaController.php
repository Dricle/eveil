<?php

namespace App\Http\Controllers;

use App\Enums\InsightKind;
use App\Enums\InsightStatus;
use App\Jobs\GenerateArticle;
use App\Models\Insight;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;

/**
 * The two answers to an article idea the Reddit scan noted: write it now,
 * or never suggest it again. Left alone, an open idea is also what the
 * article cadence picks from. `Insight` is project-scoped, so never
 * route-model-bound (`.ai/rules/controllers.md`).
 */
class IdeaController extends Controller
{
    public function write(CurrentProject $currentProject, int $idea): RedirectResponse
    {
        $found = Insight::query()->where('status', InsightStatus::Open)->findOrFail($idea);

        GenerateArticle::dispatch($currentProject->getOrFail(), null, $found->id);

        return back()->with('status', 'Writing that article. It will appear in the drafts in a few minutes.');
    }

    public function dismiss(int $idea): RedirectResponse
    {
        Insight::query()->findOrFail($idea)->update(['status' => InsightStatus::Dismissed]);

        return back();
    }

    /**
     * The scan notes far more ideas than a weekly cadence can use, so the
     * whole list can be cleared in one go.
     */
    public function dismissAll(): RedirectResponse
    {
        Insight::query()
            ->where('kind', InsightKind::Article)
            ->where('status', InsightStatus::Open)
            ->update(['status' => InsightStatus::Dismissed]);

        return back();
    }
}
