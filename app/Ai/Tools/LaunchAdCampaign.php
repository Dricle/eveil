<?php

namespace App\Ai\Tools;

use App\Models\Project;
use App\Services\Ads\AdProposals;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Proposes a new campaign, with its targeting and its first ads. The answer
 * says whether it is applying, waiting for the user, or blocked and why.
 */
class LaunchAdCampaign implements Tool
{
    public function __construct(private Project $project, private ?int $agentRunId) {}

    public function description(): Stringable|string
    {
        return <<<'TEXT'
        Proposes a new campaign on one ad account: a hypothesis to test, its
        targeting, a daily budget and two or three competing ads. Each ad is
        either new copy, or creative_id to run a creative that already exists
        unchanged (the way to try a proven winner on another network). New copy
        adapted from an existing creative passes parent_id.
        TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        return app(AdProposals::class)->launchCampaign(
            $this->project,
            $this->agentRunId,
            $request->integer('account_id'),
            $request->string('name')->value(),
            $request->string('hypothesis')->value(),
            $request->filled('target_profile_id') ? $request->integer('target_profile_id') : null,
            $request->float('daily_budget'),
            [
                'keywords' => $request->array('keywords'),
                'negative_keywords' => $request->array('negative_keywords'),
                'countries' => $request->array('countries'),
                'languages' => $request->array('languages'),
            ],
            $request->array('ads'),
            $request->string('reasoning')->value(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'account_id' => $schema->integer()->description('The ad account, from the report.')->required(),
            'name' => $schema->string()->description('Short and descriptive, e.g. "Cold email tool, FR, high intent".')->required(),
            'hypothesis' => $schema->string()->description('What this campaign tests, in one sentence you will judge it against later.')->required(),
            'target_profile_id' => $schema->integer()->description('The target profile this campaign speaks to, when it maps to one.'),
            'daily_budget' => $schema->number()->description('In the account currency units, not micros.')->required(),
            'keywords' => $schema->array()->items($schema->string())->description('Search terms with clear intent. Phrase match.'),
            'negative_keywords' => $schema->array()->items($schema->string())->description('Terms to exclude.'),
            'countries' => $schema->array()->items($schema->string())->description('ISO 3166 alpha 2 codes, e.g. FR.')->required(),
            'languages' => $schema->array()->items($schema->string())->description('ISO 639 1 codes, e.g. fr.')->required(),
            'ads' => $schema->array()->items($schema->object([
                'creative_id' => $schema->integer()->description('Run this existing creative unchanged. Leave the copy fields out.'),
                'parent_id' => $schema->integer()->description('The creative this new copy adapts, when it does.'),
                'angle' => $schema->string()->description('The idea this ad tests, in one sentence.'),
                'headlines' => $schema->array()->items($schema->string()),
                'descriptions' => $schema->array()->items($schema->string()),
                'final_url' => $schema->string()->description('A page on the product site. Defaults to its home page.'),
            ]))->required(),
            'reasoning' => $schema->string()->description('Why, citing the numbers or the gap that calls for it.')->required(),
        ];
    }
}
