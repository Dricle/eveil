<?php

namespace App\Ai\Tools;

use App\Models\Project;
use App\Services\Ads\AdPerformanceReport;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The ads report the ad agent reviews from, read only. Any change asked in
 * chat goes through `AdProposals` like every other decision, never from here.
 */
class GetAdPerformance implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return 'Reads how the ads are doing: the monthly budget, what is spent and how the month is pacing, each ad account '
            .'with its campaigns and ads (lifetime and last 7 days, CTR, CPC, conversions, a verdict per ad), each creative per network, '
            .'and the decisions of the last 30 days with their reasoning, status and the campaign numbers before and after. '
            .'Money is in the budget currency. Use it for "how are my ads doing" or "why did you pause that campaign". Read only.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (! $this->project->adAccounts()->exists()) {
            return 'No ad account is connected to this project yet.';
        }

        // ponytail: whole report, fine for a few campaigns; add a campaign_id filter if it gets too big for chat.
        return (string) json_encode(app(AdPerformanceReport::class)->build($this->project));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
