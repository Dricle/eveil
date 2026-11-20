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
 * `enrol-campaign-leads` for a list somebody chose, rather than everyone the
 * campaign's segment found. Same refusals as any enrolment, see
 * `EnrolCampaign::handleChosen()`, and the answer names who was left out so a
 * client never has to guess which half of its list went in.
 */
#[IsIdempotent]
#[Description('Add the given contacts, and only them, to a running campaign. Anyone without a usable email, opted out, suppressed or already in a sequence is left out and listed in not_enrolled. Only works on an active campaign.')]
class EnrolContacts extends Tool
{
    use ActsOnProject;

    public function handle(Request $request, EnrolCampaign $enrol): Response|ResponseFactory
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

        if ($campaign->status !== CampaignStatus::Active) {
            return Response::error('That campaign is not running. Activate it in the app first.');
        }

        if (! $enrol->canSendFor($campaign)) {
            return Response::error('This project has no active mailbox to send from. See get-campaign-sending.');
        }

        $requested = array_values(array_unique(array_map(intval(...), $validated['contact_ids'])));
        $enrolled = $enrol->handleChosen($campaign, $requested);

        return Response::structured([
            'enrolled' => count($enrolled),
            'not_enrolled' => array_values(array_diff($requested, $enrolled)),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $this->projectArgument($schema),
            'campaign_id' => $schema->integer()->description('The campaign id.')->required(),
            'contact_ids' => $schema->array()->items($schema->integer())->max(500)->description('The contacts to enrol, as list-contacts gives them.')->required(),
        ];
    }
}
