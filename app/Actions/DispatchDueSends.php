<?php

namespace App\Actions;

use App\Enums\CampaignLeadStatus;
use App\Enums\CampaignStatus;
use App\Enums\EmailAccountStatus;
use App\Enums\OrganizationRole;
use App\Jobs\SendCampaignStep;
use App\Models\CampaignLead;
use App\Models\EmailAccount;
use App\Notifications\MailboxPaused;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;

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
        // Outside working hours nothing leaves. A 04:00 mail from somebody's
        // own mailbox reads as a machine before it reads as anything else.
        if (! $this->windowIsOpen()) {
            return 0;
        }

        $queued = 0;

        // Random order: several accounts can serve the same unassigned lead
        // (see `due()`), and whichever one asks first is the one it pins to.
        // A fixed order would always feed the lowest-id mailbox first and
        // starve the rest.
        EmailAccount::query()
            ->where('status', EmailAccountStatus::Active)
            ->inRandomOrder()
            ->each(function (EmailAccount $account) use (&$queued): void {
                // The circuit breaker, ahead of any allowance arithmetic: a
                // mailbox bouncing right now must stop whatever the project's
                // autonomy level says.
                if ($account->recentBounceRate() > $account->maxBounceRate()) {
                    $account->update([
                        'status' => EmailAccountStatus::Paused,
                        'last_error' => 'Paused automatically: too many recent sends bounced.',
                    ]);

                    $this->notifyOwners($account);

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
     * Whether the sending window is open right now.
     *
     * Public because a screen has to explain a campaign that is active and
     * quiet, and asking the hour against the same setting somewhere else is how
     * a screen ends up disagreeing with what the scheduler actually does.
     */
    public function windowIsOpen(): bool
    {
        $sending = $this->settings->array('sending');
        $hour = (int) now()->format('G');

        return $hour >= (int) $sending['window_start'] && $hour < (int) $sending['window_end'];
    }

    /**
     * The breaker trips silently otherwise: nothing else watches the mailboxes
     * screen, so the owners are the only people who can reactivate it.
     */
    private function notifyOwners(EmailAccount $account): void
    {
        $owners = $account->organization->users()
            ->wherePivot('role', OrganizationRole::Owner->value)
            ->get();

        Notification::send($owners, MailboxPaused::for($account));
    }

    /**
     * The oldest thing owed by this mailbox, pinning it in if it isn't yet.
     *
     * `EnrolCampaign` leaves `email_account_id` null: this is where a lead
     * actually gets a mailbox, at its first send, so leads spread across
     * every mailbox the project has instead of all landing on whichever one
     * enrolment happened to see first. Oldest first so a follow-up promised
     * for Tuesday is not overtaken by a lead enrolled this morning.
     */
    private function due(EmailAccount $account): ?CampaignLead
    {
        $lead = CampaignLead::query()
            ->where(fn (Builder $query) => $query
                ->where('email_account_id', $account->id)
                ->orWhere(fn (Builder $unassigned) => $unassigned
                    ->whereNull('email_account_id')
                    ->whereHas('campaign', fn (Builder $campaign) => $campaign
                        ->withoutGlobalScopes()
                        ->whereHas('project', fn (Builder $project) => $project
                            ->withoutGlobalScopes()
                            ->whereHas('emailAccounts', fn (Builder $accounts) => $accounts->whereKey($account->id))))))
            ->whereIn('status', [CampaignLeadStatus::Pending, CampaignLeadStatus::Running])
            ->whereNotNull('next_action_at')
            ->where('next_action_at', '<=', now())
            ->whereHas('campaign', fn (Builder $campaign) => $campaign
                ->withoutGlobalScopes()
                ->where('status', CampaignStatus::Active))
            ->oldest('next_action_at')
            ->first();

        if ($lead !== null && $lead->email_account_id === null) {
            $lead->update(['email_account_id' => $account->id]);
        }

        return $lead;
    }
}
