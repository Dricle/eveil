<?php

namespace App\Ai\Tools;

use App\Actions\UpdateInsight as UpdateInsightAction;
use App\Enums\InsightStatus;
use App\Models\Insight;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The Insights page's buttons on one item: mark read or unread, done, or
 * dismissed. Needs no approval, same as the buttons: nothing is spawned or
 * deleted.
 */
class UpdateInsight implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return 'Marks one open Insights item read or unread, done (used) or dismissed for good. Call ListInsights first to find its id. '
            .'For a recommendation you can also use UpdateRecommendation by key.';
    }

    public function handle(Request $request): Stringable|string
    {
        $idea = Insight::query()
            ->where('project_id', $this->project->id)
            ->where('status', InsightStatus::Open)
            ->find($request->integer('insight_id'));

        if ($idea === null) {
            return 'No open Insights item with that id on this project. Call ListInsights first.';
        }

        app(UpdateInsightAction::class)->handle(
            $idea,
            $request->has('read') ? $request->boolean('read') : null,
            InsightStatus::tryFrom($request->string('status')->value()),
        );

        return "Insight #{$idea->id} updated.";
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'insight_id' => $schema->integer()->description('The item, from ListInsights.')->required(),
            'read' => $schema->boolean()->description('true to mark it read, false to mark it unread.'),
            'status' => $schema->string()->enum(['used', 'dismissed'])->description('used once acted on, dismissed to never see it again.'),
        ];
    }
}
