<?php

namespace App\Ai\Tools;

use App\Enums\IdeaStatus;
use App\Models\Idea;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The SEO page's "Dismiss" on an article idea: never suggested again. Needs
 * no approval, same as the button: nothing is spawned or deleted.
 */
class DismissArticleIdea implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return 'Dismisses an open article idea for good, so it is never suggested again - call ListArticleIdeas first to find its id.';
    }

    public function handle(Request $request): Stringable|string
    {
        $idea = Idea::query()
            ->where('project_id', $this->project->id)
            ->where('status', IdeaStatus::Open)
            ->find($request->integer('idea_id'));

        if ($idea === null) {
            return 'No open article idea with that id on this project. Call ListArticleIdeas first.';
        }

        $idea->update(['status' => IdeaStatus::Dismissed]);

        return "Idea #{$idea->id} dismissed.";
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'idea_id' => $schema->integer()->description('The idea to dismiss, from ListArticleIdeas.')->required(),
        ];
    }
}
