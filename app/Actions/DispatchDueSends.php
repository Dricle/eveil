<?php

namespace App\Actions;

use App\Enums\CampaignLeadStatus;
use App\Enums\CampaignStatus;
use App\Enums\EmailAccountStatus;
use App\Jobs\SendCampaignStep;
use App\Models\CampaignLead;
use App\Models\EmailAccount;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deciding what may go out right now: the daily cap and the spread, which are
 * the two things standing between a working mailbox and a blocked one.
 *
 * Driven per MAILBOX and not per campaign, because that is the unit the
 * receiving server counts. Three projects sharing one address share one
 * allowance; count per campaign and an address rated for thirty sends ninety.
 *
 * Called on a schedule rather than looping: a tick that sends a couple and stops
 * IS the spread. Nothing here sends anything itself: each due row becomes one
 * queued job, so a provider timeout costs one mail and not the batch.
 */
class DispatchDueSends
{
    public function __construct(private Settings $settings) {}

    /**
     * How many sends were queued.
     */
    public function handle(): int
    {
        $sending = $this->settings->array('sending');

        // Outside working hours nothing leaves. A 04:00 mail from somebody's
        // own mailbox reads as a machine before it reads as anything else.
        $hour = (int) now()->format('G');

        if ($hour < (int) $sending['window_start'] || $hour >= (int) $sending['window_end']) {
            return 0;
        }

        $queued = 0;

        EmailAccount::query()
            ->where('status', EmailAccountStatus::Active)
            ->each(function (EmailAccount $account) use ($sending, &$queued): void {
                // The circuit breaker, ahead of any allowance arithmetic: a
                // mailbox bouncing right now must stop whatever the project's
                // autonomy level says.
                if ($account->recentBounceRate() > (float) $sending['max_bounce_rate']) {
                    $account->update([
                        'status' => EmailAccountStatus::Paused,
                        'last_error' => 'Paused automatically: too many recent sends bounced.',
                    ]);

                    return;
                }

                if ($account->remainingToday() < 1 || ! $account->readyToSend()) {
                    return;
                }

                $due = $this->due($account);

                if ($due === null) {
                    return;
                }

                SendCampaignStep::dispatch($due);

                // One per mailbox per tick, deliberately. The gap between two
                // mails from one address is the whole point of pacing, and a
                // batch dispatched together would arrive together.
                $queued++;
            });

        return $queued;
    }

    /**
     * The oldest thing owed by this mailbox. Oldest first so a follow-up
     * promised for Tuesday is not overtaken by a lead enrolled this morning.
     */
    private function due(EmailAccount $account): ?CampaignLead
    {
        return CampaignLead::query()
            ->where('email_account_id', $account->id)
            ->whereIn('status', [CampaignLeadStatus::Pending, CampaignLeadStatus::Running])
            ->whereNotNull('next_action_at')
            ->where('next_action_at', '<=', now())
            ->whereHas('campaign', fn (Builder $campaign) => $campaign
                ->withoutGlobalScopes()
                ->where('status', CampaignStatus::Active))
            ->oldest('next_action_at')
            ->first();
    }
}
