<?php

namespace App\Mcp\Tools;

use App\Actions\EnrolCampaign;
use App\Enums\CampaignStatus;
use App\Mcp\Tools\Concerns\ActsOnProject;
use App\Models\Campaign;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

/**
 * The app's "add the people found since" button, active campaigns only, like
 * `POST /api/v1/campaigns/{id}/enrolments`. Idempotent: a second call finds
 * nobody left to add.
 */
#[IsIdempotent]
#[Description('Add everyone eligible found since the campaign started to a running campaign. They start receiving its emails on schedule. Only works on an active campaign; returns how many joined.')]
class EnrolCampaignLeads extends Tool
{
    use ActsOnProject;

    public function handle(Request $request, EnrolCampaign $enrol): Response|ResponseFactory
    {
        $request->validate(['campaign_id' => ['required', 'integer']]);

        $campaign = Campaign::query()->find($request->integer('campaign_id'));

        if ($campaign === null) {
            return Response::error('No campaign with that id in this project.');
        }

        if ($campaign->status !== CampaignStatus::Active) {
            return Response::error('That campaign is not running. Activate it in the app first.');
        }

        return Response::structured(['enrolled' => $enrol->handle($campaign)]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $this->projectArgument($schema),
            'campaign_id' => $schema->integer()->description('The campaign id.')->required(),
        ];
    }
}
