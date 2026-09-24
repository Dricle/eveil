<?php

namespace App\Ai\Tools;

use App\Models\Article;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * This project's SEO articles, without their bodies: an article runs to a
 * thousand words and a list of ten would flood the conversation. GetArticle
 * reads one in full.
 */
class ListArticles implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return 'Lists this project\'s SEO articles (drafts, published, rejected) with their id, status, title, source and published URL, most recent first. Call GetArticle to read one in full.';
    }

    public function handle(Request $request): Stringable|string
    {
        $articles = Article::query()
            ->where('project_id', $this->project->id)
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (Article $article): array => [
                'id' => $article->id,
                'status' => $article->status->value,
                'title' => $article->title,
                'source_type' => $article->source_type->value,
                'evidence' => $article->evidence,
                'published_url' => $article->published_url,
            ]);

        if ($articles->isEmpty()) {
            return 'This project has no articles yet.';
        }

        return (string) json_encode($articles->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
