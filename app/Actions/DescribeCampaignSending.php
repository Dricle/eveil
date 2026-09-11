<?php

namespace App\Actions;

use App\Enums\CampaignLeadStatus;
use App\Http\Resources\MailboxResource;
use App\Models\Campaign;
use App\Support\AggregateDate;
use App\Support\Settings;

/**
 * Everything the delivery screen's answer to "when does the next one go out"
 * is made of.
 *
 * The rules themselves are the scheduler's: the window comes from the action
 * that enforces it, and the allowance and the gap from the mailbox. Restating
 * any of them here is how a screen ends up promising a send the scheduler
 * will not make.
 */
class DescribeCampaignSending
{
    public function __construct(private Settings $settings) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Campaign $campaign, DispatchDueSends $dispatcher): array
    {
        // The project's own mailboxes, not just whichever ones already have a
        // pinned lead: `DispatchDueSends` only pins one at first send, so a
        // freshly-activated campaign would otherwise show none at all.
        $mailboxes = $campaign->project->emailAccounts;

        return [
            // Parsed rather than passed through: an aggregate comes back as a
            // raw database string, and every other date on the page is a cast
            // attribute. Two formats reach the same date formatter otherwise.
            'next_action_at' => AggregateDate::parse($campaign->campaignLeads()
                ->whereIn('status', CampaignLeadStatus::live())
                ->min('next_action_at')),
            'window_open' => $dispatcher->windowIsOpen(),
            'window' => [
                'start' => (int) $this->settings->array('sending')['window_start'],
                'end' => (int) $this->settings->array('sending')['window_end'],
            ],
            'mailboxes' => MailboxResource::collection($mailboxes),
        ];
    }
}
