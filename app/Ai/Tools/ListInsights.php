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
 * The Insights feed, what its page lists: open items, newest first.
 */
class ListInsights implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return 'Lists the open items of the Insights feed, newest first: what people say about the competitors on Reddit '
            .'(competitor_mention), features competitors have and this product lacks (feature), acquisition levers '
            .'missing from the site (acquisition), and article topics many threads keep asking for (article; the full list is ListArticleIdeas). Use it to answer "what do people think '
            .'of competitor X" or "what is new". Dismissed and acted-on items are not listed. '
            .'Pass insight_id to read one item, for example one the user pasted.';
    }

    public function handle(Request $request): Stringable|string
    {
        $kind = InsightKind::tryFrom($request->string('kind')->value());
        $competitor = trim($request->string('competitor')->value());

        $insights = Insight::query()
            ->where('project_id', $this->project->id)
            ->where('status', InsightStatus::Open)
            ->when($request->integer('insight_id'), fn ($query, int $id) => $query->whereKey($id), fn ($query) => $query->inFeed())
            ->when($kind, fn ($query, InsightKind $kind) => $query->where('kind', $kind))
            ->when($competitor !== '', fn ($query) => $query->whereRaw("lower(details->>'competitor') = ?", [mb_strtolower($competitor)]))
            ->when($request->boolean('unread_only'), fn ($query) => $query->whereNull('read_at'))
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn (Insight $idea): array => array_filter([
                'id' => $idea->id,
                'kind' => $idea->kind->value,
                'title' => $idea->title,
                'summary' => $idea->angle,
                'url' => $idea->url,
                'details' => $idea->details,
                'read' => $idea->read_at !== null,
                'noted_at' => $idea->created_at?->toDateString(),
            ], fn (mixed $value): bool => $value !== null));

        if ($insights->isEmpty()) {
            return 'Nothing in the Insights feed matches.';
        }

        return (string) json_encode($insights->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'insight_id' => $schema->integer()->description('Only this item, by id, even past the newest 30.'),
            'kind' => $schema->string()->enum(array_column(InsightKind::cases(), 'value'))->description('Only this kind. Omit for everything.'),
            'competitor' => $schema->string()->description('Only mentions of this competitor, as the knowledge base names it.'),
            'unread_only' => $schema->boolean()->description('Only items the user has not read yet.'),
        ];
    }
}
