<?php

namespace App\Ai\Tools;

use App\Models\Project;
use App\Services\Ads\AdProposals;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Pauses one ad. Cutting spend never waits for approval.
 */
class PauseAd implements Tool
{
    public function __construct(private Project $project, private ?int $agentRunId) {}

    public function description(): Stringable|string
    {
        return 'Pauses one running ad that has had a fair chance and underperforms. Applied at once.';
    }

    public function handle(Request $request): Stringable|string
    {
        return app(AdProposals::class)->pauseAd($this->project, $this->agentRunId, $request->integer('ad_id'), $request->string('reasoning')->value());
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'ad_id' => $schema->integer()->required(),
            'reasoning' => $schema->string()->description('Why, citing the numbers.')->required(),
        ];
    }
}
