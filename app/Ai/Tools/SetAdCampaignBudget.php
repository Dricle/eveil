<?php

namespace App\Ai\Tools;

use App\Models\Project;
use App\Services\Ads\AdProposals;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Changes a campaign's daily budget. A cut applies at once; a raise is
 * capped per step and must fit the month.
 */
class SetAdCampaignBudget implements Tool
{
    public function __construct(private Project $project, private ?int $agentRunId) {}

    public function description(): Stringable|string
    {
        return 'Sets a campaign\'s daily budget: up for a winner, down for one that wastes. A raise is capped per step and must fit what is left of the month.';
    }

    public function handle(Request $request): Stringable|string
    {
        return app(AdProposals::class)->setDailyBudget(
            $this->project,
            $this->agentRunId,
            $request->integer('campaign_id'),
            $request->float('daily_budget'),
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
            'daily_budget' => $schema->number()->description('The new daily budget in the account currency units, not micros.')->required(),
            'reasoning' => $schema->string()->description('Why, citing the numbers.')->required(),
        ];
    }
}
