<?php

namespace App\Ai\Tools;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Rewrites an article draft in place, on the user's feedback - what the SEO
 * page's own edit does. Draft only, same reasoning as `UpdateLinkedinPost`:
 * a published article is already live on the user's site, and changing the
 * copy here would no longer match what is there.
 */
class UpdateArticle implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return <<<'TEXT'
        Rewrites an existing article draft - call GetArticle first to read it.
        Pass only what changes: a new title, a new meta description, and/or the
        full new Markdown body (the whole article, not just the edited part).
        Refuses once the article is published or rejected.
        TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        $article = Article::query()
            ->where('project_id', $this->project->id)
            ->find($request->integer('article_id'));

        if ($article === null) {
            return 'No article with that id exists on this project. Call ListArticles first.';
        }

        if ($article->status !== ArticleStatus::Draft) {
            return "That article is already {$article->status->value}, so it can no longer be edited here.";
        }

        $changes = array_filter([
            'title' => $request->string('title')->trim()->value(),
            'meta_description' => $request->string('meta_description')->trim()->value(),
            'body' => $request->string('body')->trim()->value(),
        ], fn (string $value): bool => $value !== '');

        if ($changes === []) {
            return 'Nothing to change: pass a title, a meta description or a body.';
        }

        $article->update($changes);

        return "Article #{$article->id} updated.";
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'article_id' => $schema->integer()->description('The draft to update, from ListArticles.')->required(),
            'title' => $schema->string()->description('A new title, only if it changes.'),
            'meta_description' => $schema->string()->description('A new meta description, only if it changes.'),
            'body' => $schema->string()->description('The full new Markdown body, only if it changes.'),
        ];
    }
}
