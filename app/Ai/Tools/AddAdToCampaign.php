<?php

namespace App\Ai\Tools;

use App\Models\Project;
use App\Services\Ads\AdProposals;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Proposes one more ad in a campaign: a challenger to what runs, or a
 * creative proven elsewhere.
 */
class AddAdToCampaign implements Tool
{
    public function __construct(private Project $project, private ?int $agentRunId) {}

    public function description(): Stringable|string
    {
        return <<<'TEXT'
        Proposes a new ad in an existing campaign: a variant that changes one
        thing against what runs, a fresh angle when an ad tires, or creative_id
        to run a creative proven elsewhere unchanged. New copy adapted from an
        existing creative passes parent_id.
        TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        return app(AdProposals::class)->addAd(
            $this->project,
            $this->agentRunId,
            $request->integer('campaign_id'),
            $request->except(['campaign_id', 'reasoning']),
            $request->string('reasoning')->value(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'campaign_id' => $schema->integer()->required(),
            'creative_id' => $schema->integer()->description('Run this existing creative unchanged. Leave the copy fields out.'),
            'parent_id' => $schema->integer()->description('The creative this new copy adapts, when it does.'),
            'angle' => $schema->string()->description('The idea this ad tests, in one sentence.'),
            'headlines' => $schema->array()->items($schema->string()),
            'descriptions' => $schema->array()->items($schema->string()),
            'final_url' => $schema->string()->description('A page on the product site. Defaults to its home page.'),
            'reasoning' => $schema->string()->description('Why, citing the numbers that call for it.')->required(),
        ];
    }
}
