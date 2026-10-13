<?php

namespace App\Ai\Tools;

use App\Actions\AddRecommendation;
use App\Enums\RecommendationKind;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
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
        Adds a new idea to the project's open list, the same shape
        WebsiteAnalyst proposes: a concrete lever, evidence for why it's
        missing, and an impact/effort ranking. kind "feature" is for a product
        capability competitors have and this product lacks; anything else is
        an acquisition lever. Only for a lever genuinely new
        to the list - read GetKnowledgeBase first and use UpdateRecommendation
        instead when the user is really asking to change one that's already
        there.
        TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        $key = app(AddRecommendation::class)->handle($this->project, [
            'idea' => $request->string('idea')->value(),
            'evidence' => $request->string('evidence')->value(),
            'impact' => $request->string('impact')->value(),
            'effort' => $request->string('effort')->value(),
            'kind' => $request->string('kind', RecommendationKind::Acquisition->value)->value(),
        ]);

        return "Added, key \"{$key}\".";
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
            'kind' => $schema->string()->enum(['acquisition', 'feature'])
                ->description('"feature" for a capability competitors have that this product lacks. Defaults to "acquisition".'),
        ];
    }
}
