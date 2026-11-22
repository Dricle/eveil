<?php

namespace App\Mcp\Tools;

use App\Enums\CampaignLeadStatus;
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
 * Taking people back out of a sequence, for the ones who should never have
 * been in it. Stopped, not deleted: the contact, its company and whatever was
 * already sent stay where they are, and nothing more goes out from this
 * campaign.
 */
#[IsIdempotent]
#[Description('Stop the campaign for the given contacts: they receive nothing more from it. Nothing is deleted. Takes contact ids, as list-campaign-leads gives them in contact_id; returns how many were stopped.')]
class RemoveCampaignLeads extends Tool
{
    use ActsOnProject;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'campaign_id' => ['required', 'integer'],
            'contact_ids' => ['required', 'array', 'min:1', 'max:500'],
            'contact_ids.*' => ['integer'],
        ]);

        $campaign = Campaign::query()->find($request->integer('campaign_id'));

        if ($campaign === null) {
            return Response::error('No campaign with that id in this project.');
        }

        $removed = $campaign->campaignLeads()
            ->whereIn('lead_id', $validated['contact_ids'])
            ->whereIn('status', CampaignLeadStatus::live())
            ->update([
                'status' => CampaignLeadStatus::Stopped,
                'pause_reason' => 'removed',
                'next_action_at' => null,
            ]);

        return Response::structured(['removed' => $removed]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $this->projectArgument($schema),
            'campaign_id' => $schema->integer()->description('The campaign id.')->required(),
            'contact_ids' => $schema->array()->items($schema->integer())->max(500)->description('The contacts to take out of it.')->required(),
        ];
    }
}
