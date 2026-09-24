<?php

namespace App\Http\Controllers;

use App\Enums\ArticleStatus;
use App\Enums\IdeaKind;
use App\Enums\IdeaStatus;
use App\Http\Requests\ArticlePublishRequest;
use App\Http\Requests\ArticleRejectRequest;
use App\Http\Requests\ArticleRequest;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\IdeaResource;
use App\Jobs\FetchPublishedArticle;
use App\Jobs\GenerateArticle;
use App\Models\Article;
use App\Models\Idea;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The SEO page, and the queue every drafted article lands in. Nothing
 * publishes on its own: the user copies the article into their own CMS,
 * then gives back the URL it went live at.
 *
 * `Article` is project-scoped, so per `.ai/rules/controllers.md` it is never
 * route-model-bound. Every action on one row answers with `back()`: the same
 * rows are reviewed from the dashboard's to-review modal too.
 */
class ArticleController extends Controller
{
    public function index(CurrentProject $currentProject): Response
    {
        return Inertia::render('seo/Index', [
            'articles' => ArticleResource::collection(Article::query()->latest()->get()),
            'ideas' => IdeaResource::collection(
                Idea::query()->where('kind', IdeaKind::Article)->where('status', IdeaStatus::Open)->latest()->get()
            ),
            'frequency' => $currentProject->getOrFail()->article_frequency->value,
        ]);
    }

    public function generate(CurrentProject $currentProject): RedirectResponse
    {
        GenerateArticle::dispatch($currentProject->getOrFail());

        return to_route('seo.index')->with('status', 'Writing an article. It will appear here in a few minutes.');
    }

    public function update(ArticleRequest $request, int $article): RedirectResponse
    {
        Article::query()->where('status', ArticleStatus::Draft)->findOrFail($article)->update($request->validated());

        return back();
    }

    /**
     * "I published it": the URL is what the next draft reads to avoid
     * writing the same article again, and it is fetched into the shared
     * page cache in the background.
     */
    public function publish(ArticlePublishRequest $request, int $article): RedirectResponse
    {
        $url = $request->validated('published_url');

        Article::query()->findOrFail($article)->update([
            'status' => ArticleStatus::Published,
            'published_url' => $url,
            'published_at' => now(),
        ]);

        FetchPublishedArticle::dispatch($url);

        return back();
    }

    /**
     * Keeps the row, with an optional reason the writer reads next time -
     * same "reject vs delete" distinction as `LinkedinPostController`.
     */
    public function reject(ArticleRejectRequest $request, int $article): RedirectResponse
    {
        Article::query()->findOrFail($article)->update([
            'status' => ArticleStatus::Rejected,
            'rejection_reason' => $request->validated('reason'),
        ]);

        return back();
    }

    public function destroy(int $article): RedirectResponse
    {
        Article::query()->findOrFail($article)->delete();

        return back();
    }
}
