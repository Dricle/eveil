<?php

namespace App\Ai\Tools;

use App\Models\Project;
use App\Services\Ads\AdProposals;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Pauses a whole campaign. Cutting spend never waits for approval.
 */
class PauseAdCampaign implements Tool
{
    public function __construct(private Project $project, private ?int $agentRunId) {}

    public function description(): Stringable|string
    {
        return 'Pauses a running campaign whose hypothesis failed, freeing its budget. Applied at once.';
    }

    public function handle(Request $request): Stringable|string
    {
        return app(AdProposals::class)->pauseCampaign($this->project, $this->agentRunId, $request->integer('campaign_id'), $request->string('reasoning')->value());
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'campaign_id' => $schema->integer()->required(),
            'reasoning' => $schema->string()->description('Why, citing the numbers.')->required(),
        ];
    }
}
