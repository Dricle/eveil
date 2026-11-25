<?php

namespace App\Mcp\Tools;

use App\Actions\DescribeCampaignSending;
use App\Actions\DispatchDueSends;
use App\Actions\EnrolCampaign;
use App\Mcp\Tools\Concerns\ActsOnProject;
use App\Models\Campaign;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The delivery screen's "when does the next one go out", for a client that
 * sees a campaign full of pending people and nothing sent. The answer is
 * almost always a mailbox: paused by the bounce breaker, failing to connect,
 * out of today's allowance, or not attached to the project at all.
 */
#[IsReadOnly]
#[Description('Why a campaign is or is not sending: the project\'s mailboxes with their status, last error and remaining allowance today, the sending window (window_open: whether anyone owed a mail right now is inside it on their own clock), and when the next email is due. can_send is false when no active mailbox is attached to the project. blocked_by_credits says why nothing goes out when the organization has no credits left, null otherwise.')]
class GetCampaignSending extends Tool
{
    use ActsOnProject;

    public function handle(Request $request, DescribeCampaignSending $describe, DispatchDueSends $dispatcher, EnrolCampaign $enrol): Response|ResponseFactory
    {
        $request->validate(['campaign_id' => ['required', 'integer']]);

        $campaign = Campaign::query()->find($request->integer('campaign_id'));

        if ($campaign === null) {
            return Response::error('No campaign with that id in this project.');
        }

        $sending = $describe->handle($campaign, $dispatcher);

        return Response::structured([
            'status' => $campaign->status,
            'can_send' => $enrol->canSendFor($campaign),
            'next_action_at' => $sending['next_action_at']?->toIso8601String(),
            'window_open' => $sending['window_open'],
            'window' => $sending['window'],
            'mailboxes' => $sending['mailboxes']->resolve(),
            'blocked_by_credits' => $sending['blocked_by_credits'],
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
        ];
    }
}
