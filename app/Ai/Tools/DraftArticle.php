<?php

namespace App\Ai\Tools;

use App\Enums\IdeaStatus;
use App\Jobs\GenerateArticle;
use App\Models\Idea;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Queues a new article about what the user just told Evie ("we just shipped
 * X"), or about one of the open article ideas. Evie writes the brief, not the article: `ArticleWriter` writes every
 * article, so one from chat reads like one from the cadence, and a thousand
 * words never have to pass through a chat turn. Needs no approval, same
 * reasoning as `DraftLinkedinPost`: it only drafts, nothing is published.
 */
class DraftArticle implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return <<<'TEXT'
        Queues a new SEO article for the project's blog about what the user just
        told you - a feature that shipped, a topic they want covered. Pass a brief:
        what the article is about and every concrete detail the user gave. It is
        written in the background and lands on the SEO page as a draft in a few
        minutes; it is never published on its own. To write one of the open
        article ideas instead (ListArticleIdeas), pass its idea_id and no brief.
        TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        $ideaId = $request->integer('idea_id') ?: null;

        if ($ideaId !== null) {
            $idea = Idea::query()
                ->where('project_id', $this->project->id)
                ->where('status', IdeaStatus::Open)
                ->find($ideaId);

            if ($idea === null) {
                return 'No open article idea with that id on this project. Call ListArticleIdeas first.';
            }

            GenerateArticle::dispatch($this->project, null, $idea->id);
        } else {
            $brief = trim($request->string('brief')->value());

            if ($brief === '') {
                return 'Pass either a brief or an idea_id.';
            }

            GenerateArticle::dispatch($this->project, $brief);
        }

        return 'The article is being written. It will appear as a draft on the SEO page in a few minutes.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'brief' => $schema->string()
                ->description('What the article is about, with every concrete detail the user gave: the feature, who it is for, what problem it solves. Leave out when passing idea_id.'),
            'idea_id' => $schema->integer()
                ->description('An open article idea to write, from ListArticleIdeas. Leave out when passing a brief.'),
        ];
    }
}
