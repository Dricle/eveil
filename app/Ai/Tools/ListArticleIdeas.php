<?php

namespace App\Ai\Tools;

use App\Enums\InsightKind;
use App\Enums\InsightStatus;
use App\Models\Insight;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The open article topics the Reddit scan noted, the most asked first: what
 * the SEO page lists at its top.
 */
class ListArticleIdeas implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return 'Lists the open article ideas, the most asked first: topics Reddit threads asked for, each with its id, angle, how many threads asked (mentions) and those threads. Used and dismissed ideas are not listed.';
    }

    public function handle(Request $request): Stringable|string
    {
        $ideas = Insight::query()
            ->where('project_id', $this->project->id)
            ->where('kind', InsightKind::Article)
            ->where('status', InsightStatus::Open)
            ->orderByDesc('mentions')
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (Insight $idea): array => [
                'id' => $idea->id,
                'angle' => $idea->angle,
                'mentions' => $idea->mentions,
                'threads' => $idea->threads(),
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
