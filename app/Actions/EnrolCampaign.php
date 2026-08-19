<?php

namespace App\Actions;

use App\Enums\CampaignLeadStatus;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignLead;
use App\Models\EmailAccount;
use App\Models\Lead;
use App\Services\Outreach\SuppressionList;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;

/**
 * Putting the people into a sequence, which is what activating a campaign
 * actually means.
 *
 * The mailbox is chosen once per lead and pinned for the whole sequence: a
 * follow-up from a different address is a different conversation as far as
 * threading is concerned, and the reply we are waiting for would arrive in a
 * mailbox nobody is reading for it.
 *
 * Nothing is enrolled that must not be written to — an existing client, a
 * bounce, an opt-out, an address nobody could verify. Checking here is not a
 * substitute for checking at send time (a STOP can arrive in between, which is
 * exactly the case that matters) but it keeps a campaign from claiming to be
 * about five hundred people when four hundred of them are unreachable.
 */
class EnrolCampaign
{
    public function __construct(private SuppressionList $suppressions) {}

    /**
     * How many leads joined.
     */
    public function handle(Campaign $campaign): int
    {
        $account = EmailAccount::query()
            ->sendableFor($campaign->project)
            ->orderBy('id')
            ->first();

        // No mailbox attached to this project, so there is nothing to send
        // from. Deliberately not an exception: the campaign stays active and
        // starts sending by itself once a mailbox is attached.
        if ($account === null) {
            return 0;
        }

        $enrolled = 0;

        Lead::query()
            ->contactable()
            ->whereNotNull('email')
            ->whereDoesntHave('campaignLeads', fn ($query) => $query->whereIn('status', CampaignLeadStatus::live()))
            ->with('project')
            ->each(function (Lead $lead) use ($campaign, $account, &$enrolled): void {
                if (! $lead->isSendable() || $this->suppressions->suppresses($lead, $account)) {
                    return;
                }

                try {
                    CampaignLead::query()->create([
                        'campaign_id' => $campaign->id,
                        'lead_id' => $lead->id,
                        'email_account_id' => $account->id,
                        'current_step_position' => 0,
                        'status' => CampaignLeadStatus::Pending,
                        // Spread from the outset rather than all at once: the
                        // dispatcher paces the day, and this keeps a thousand
                        // rows from all claiming the same minute.
                        'next_action_at' => $this->stagger($enrolled),
                    ]);

                    $enrolled++;
                } catch (QueryException) {
                    // The one-live-campaign-per-lead index said no: this person
                    // is already in a sequence, and being found twice is not a
                    // reason to write to them twice.
                }
            });

        if ($enrolled > 0 && $campaign->status !== CampaignStatus::Active) {
            $campaign->update(['status' => CampaignStatus::Active]);
        }

        return $enrolled;
    }

    private function stagger(int $position): CarbonInterface
    {
        return now()->addMinutes($position * 3);
    }
}
