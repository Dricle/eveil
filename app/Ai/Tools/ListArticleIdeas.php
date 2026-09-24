<?php

namespace App\Ai\Tools;

use App\Enums\IdeaKind;
use App\Enums\IdeaStatus;
use App\Models\Idea;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The open article ideas the Reddit scan noted, what the SEO page lists at
 * its top.
 */
class ListArticleIdeas implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return 'Lists the open article ideas: Reddit discussions the scan noted as worth a blog article, with their id, angle and thread link. Used and dismissed ideas are not listed.';
    }

    public function handle(Request $request): Stringable|string
    {
        $ideas = Idea::query()
            ->where('project_id', $this->project->id)
            ->where('kind', IdeaKind::Article)
            ->where('status', IdeaStatus::Open)
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (Idea $idea): array => [
                'id' => $idea->id,
                'angle' => $idea->angle,
                'thread_title' => $idea->title,
                'thread_url' => $idea->source_ref,
            ]);

        if ($ideas->isEmpty()) {
            return 'There are no open article ideas.';
        }

        return (string) json_encode($ideas->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
