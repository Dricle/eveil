<?php

namespace App\Http\Controllers;

use App\Http\Requests\ArticleFrequencyRequest;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;

/**
 * How often the current project wants a new article drafted. Same shape as
 * `RedditCadenceController`, including resetting `article_next_at` to now:
 * nothing else ever sets it, so a cadence just turned on would otherwise
 * never be picked up.
 */
class ArticleCadenceController extends Controller
{
    public function update(ArticleFrequencyRequest $request, CurrentProject $currentProject): RedirectResponse
    {
        $currentProject->getOrFail()->update([
            ...$request->validated(),
            'article_next_at' => now(),
        ]);

        return to_route('seo.index');
    }
}
