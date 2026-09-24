<?php

namespace App\Ai\Tools;

use App\Models\Article;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * One article in full, what the user is looking at when they ask Evie to
 * rework it.
 */
class GetArticle implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return 'Reads one SEO article in full: title, meta description, Markdown body, status and why it was written.';
    }

    public function handle(Request $request): Stringable|string
    {
        $article = Article::query()
            ->where('project_id', $this->project->id)
            ->find($request->integer('article_id'));

        if ($article === null) {
            return 'No article with that id exists on this project. Call ListArticles first.';
        }

        return (string) json_encode([
            'id' => $article->id,
            'status' => $article->status->value,
            'title' => $article->title,
            'meta_description' => $article->meta_description,
            'evidence' => $article->evidence,
            'language' => $article->language,
            'body' => $article->body,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'article_id' => $schema->integer()->description('The article to read, from ListArticles.')->required(),
        ];
    }
}
