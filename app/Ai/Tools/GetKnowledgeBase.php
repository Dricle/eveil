<?php

namespace App\Ai\Tools;

use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Read-only: the product portrait target profiles and sequences are written
 * from - what it does, who it's for, its features, positioning - plus the
 * acquisition ideas still awaiting a decision. Read this before proposing
 * anything to `UpdateKnowledgeBase` or `UpdateRecommendation`, same reasoning
 * as `GetCampaign` before `UpdateSequence`: never guess what it currently says.
 */
class GetKnowledgeBase implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return 'Reads this project\'s knowledge base: the product portrait target profiles and sequences are derived from.';
    }

    public function handle(Request $request): Stringable|string
    {
        $knowledgeBase = $this->project->knowledge_base;

        if ($knowledgeBase === null) {
            return 'This project has no knowledge base yet.';
        }

        return (string) json_encode([
            'what_it_does' => $knowledgeBase['what_it_does'] ?? null,
            'who_it_is_for' => $knowledgeBase['who_it_is_for'] ?? null,
            'value_proposition' => $knowledgeBase['value_proposition'] ?? null,
            'positioning' => $knowledgeBase['positioning'] ?? null,
            'pricing_model' => $knowledgeBase['pricing_model'] ?? null,
            'key_features' => $knowledgeBase['key_features'] ?? [],
            'competitors' => $knowledgeBase['competitors'] ?? [],
            'proof_points' => $knowledgeBase['proof_points'] ?? [],
            'open_questions' => $this->project->openQuestions(),
            'open_recommendations' => $this->project->openRecommendations(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
