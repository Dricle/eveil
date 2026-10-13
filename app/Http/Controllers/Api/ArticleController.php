<?php

namespace App\Http\Controllers\Api;

use App\Enums\ArticleStatus;
use App\Enums\IdeaKind;
use App\Enums\IdeaStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ArticleStoreRequest;
use App\Http\Requests\Api\ArticleUpdateRequest;
use App\Http\Resources\ArticleResource;
use App\Jobs\GenerateArticle;
use App\Models\Article;
use App\Models\Idea;
use App\Support\CurrentProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The blog articles of the SEO page, newest first. Asking for one queues it,
 * about a brief or an open idea, and answers before it is written: the draft
 * appears in the list a few minutes later. Only a draft's text can change.
 */
class ArticleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ArticleResource::collection(Article::query()->latest('id')->paginate(50));
    }

    public function show(int $article): ArticleResource
    {
        return ArticleResource::make(Article::query()->findOrFail($article));
    }

    public function store(ArticleStoreRequest $request, CurrentProject $currentProject): JsonResponse
    {
        GenerateArticle::dispatch(
            $currentProject->getOrFail(),
            $request->validated('brief'),
            $request->filled('idea_id')
                ? Idea::query()->where('kind', IdeaKind::Article)->where('status', IdeaStatus::Open)->findOrFail($request->integer('idea_id'))->id
                : null,
        );

        return response()->json(['message' => 'Queued.'], 202);
    }

    public function update(ArticleUpdateRequest $request, int $article): JsonResponse|ArticleResource
    {
        $article = Article::query()->findOrFail($article);

        if ($article->status !== ArticleStatus::Draft) {
            return response()->json(['message' => "That article is already {$article->status->value}, so it can no longer be edited."], 409);
        }

        $article->update($request->validated());

        return ArticleResource::make($article);
    }

    public function destroy(int $article): Response
    {
        Article::query()->findOrFail($article)->delete();

        return response()->noContent();
    }
}
