<?php

namespace App\Ai\Tools;

use App\Enums\RecommendationStatus;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * A new acquisition lever, grounded in something specific the conversation
 * just surfaced - never the generic playbook `WebsiteAnalyst` itself is told
 * not to write. Needs no approval, same reasoning as `UpdateKnowledgeBase`:
 * nothing is spawned, and a wrong one is one archive away from gone.
 */
class ProposeRecommendation implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return <<<'TEXT'
        Adds a new acquisition idea to the project's open list, the same shape
        WebsiteAnalyst proposes: a concrete lever, evidence for why it's
        missing, and an impact/effort ranking. Only for a lever genuinely new
        to the list - read GetKnowledgeBase first and use UpdateRecommendation
        instead when the user is really asking to change one that's already
        there.
        TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        $existingKeys = collect($this->project->recommendations())->pluck('key')->all();

        $key = $this->uniqueKey($request->string('idea')->value(), $existingKeys);

        $recommendations = [
            ...$this->project->recommendations(),
            [
                'key' => $key,
                'idea' => $request->string('idea')->value(),
                'evidence' => $request->string('evidence')->value(),
                'impact' => $request->string('impact')->value(),
                'effort' => $request->string('effort')->value(),
                'status' => RecommendationStatus::Proposed->value,
            ],
        ];

        $this->project->update([
            'knowledge_base' => [...$this->project->knowledge_base ?? [], 'recommendations' => $recommendations],
        ]);

        return "Added, key \"{$key}\".";
    }

    /**
     * @param  array<int, string>  $existingKeys
     */
    private function uniqueKey(string $idea, array $existingKeys): string
    {
        $base = Str::slug($idea, '_') ?: 'idea';
        $key = $base;

        for ($suffix = 2; in_array($key, $existingKeys, true); $suffix++) {
            $key = "{$base}_{$suffix}";
        }

        return $key;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'idea' => $schema->string()
                ->description('The concrete acquisition lever, named as a person would act on it.')
                ->required(),
            'evidence' => $schema->string()
                ->description('What specifically shows this is missing - never generic advice.')
                ->required(),
            'impact' => $schema->string()->enum(['high', 'medium', 'low'])
                ->description('How much reaching a new audience this way would plausibly move the needle.')
                ->required(),
            'effort' => $schema->string()->enum(['high', 'medium', 'low'])
                ->description('How much building or running this lever would plausibly take.')
                ->required(),
        ];
    }
}
