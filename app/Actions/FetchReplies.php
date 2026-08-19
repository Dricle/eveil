<?php

namespace App\Actions;

use App\Enums\CampaignLeadStatus;
use App\Enums\EmailAccountStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\OutreachStatus;
use App\Enums\ReplyClassification;
use App\Jobs\HandleReply;
use App\Models\EmailAccount;
use App\Models\Message;
use App\Services\Outreach\BounceReport;
use App\Services\Outreach\ImapClient;
use App\Services\Outreach\ImapFailure;
use App\Services\Outreach\InboundMail;
use App\Services\Outreach\OptOutPhrases;
use App\Services\Outreach\ReplyOutcomes;
use App\Services\Outreach\SuppressionList;
use App\Support\CurrentProject;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reading one mailbox and turning what answers our own mails into rows.
 *
 * Attribution is by header — our `Message-ID` on the way out against
 * `In-Reply-To` / `References` on the way back — never by address. Two people at
 * one company reply from the same domain, a lead forwards our mail to a
 * colleague who answers, and an address that appears in a thread is not evidence
 * of which mail it answers.
 *
 * The order here is the part that matters:
 *
 *   1. Record the reply.
 *   2. Pause the sequence, deterministically, unless the headers say a machine
 *      sent it.
 *   3. Suppress immediately when the words are an unmistakable opt-out.
 *   4. Only then ask the agent what it all means.
 *
 * Steps 2 and 3 do not depend on a provider being up, a quota being unspent, or
 * a model reading a sentence correctly. Compliance and "do not mail somebody who
 * just answered" are not allowed to be someone else's uptime.
 */
class FetchReplies
{
    public function __construct(
        private ImapClient $imap,
        private OptOutPhrases $optOut,
        private ReplyOutcomes $outcomes,
        private SuppressionList $suppressions,
        private CurrentProject $currentProject,
    ) {}

    /**
     * How many replies were attributed to a lead.
     */
    public function handle(EmailAccount $account): int
    {
        try {
            $mails = $this->imap->fetchSince($account, $account->last_inbound_uid);
        } catch (ImapFailure $failure) {
            // The same fields the send half and the connection test use: a
            // mailbox that stopped answering is one problem to the user, not two.
            $account->update([
                'status' => EmailAccountStatus::Error,
                'last_error' => 'IMAP: '.$failure->getMessage(),
                'last_checked_at' => now(),
            ]);

            return 0;
        }

        $attributed = 0;

        foreach ($mails as $mail) {
            if ($this->record($account, $mail)) {
                $attributed++;
            }

            // Advanced per mail rather than at the end: a crash halfway through
            // must not mean re-reading what was already recorded, and the unique
            // index on `message_id` is the second line of that defence.
            $account->update(['last_inbound_uid' => $mail->uid]);
        }

        $account->update(['last_checked_at' => now()]);

        return $attributed;
    }

    private function record(EmailAccount $account, InboundMail $mail): bool
    {
        // A delivery failure arrives hours after the send it is about and is not
        // an answer to anything. Handled before attribution because it names its
        // own original mail and has no reply to attribute.
        if ($mail->bounce !== null) {
            return $this->recordBounce($account, $mail->bounce);
        }

        if ($mail->inReplyTo === null) {
            return false;
        }

        $ours = Message::query()
            ->where('email_account_id', $account->id)
            ->where('direction', MessageDirection::Outbound)
            ->where('message_id', $mail->inReplyTo)
            ->with(['lead', 'campaignLead'])
            ->first();

        // Not an answer to anything we sent: a newsletter, an invoice, a
        // colleague. The user's mailbox is their own and we leave it alone.
        if ($ours === null) {
            return false;
        }

        // Asked before inserting rather than catching the unique violation:
        // Postgres aborts the whole transaction on a failed insert, so a caught
        // constraint error leaves every later query in the same transaction
        // failing too. The unique index on `message_id` stays as the backstop
        // for a genuine race between two workers.
        $reply = Message::query()->firstOrCreate([
            'message_id' => $mail->messageId,
        ], [
            'lead_id' => $ours->lead_id,
            'campaign_lead_id' => $ours->campaign_lead_id,
            'email_account_id' => $account->id,
            'direction' => MessageDirection::Inbound,
            'in_reply_to' => $mail->inReplyTo,
            'subject' => $mail->subject,
            'body' => $mail->body,
            'received_at' => now(),
        ]);

        // Already recorded: the UID moved on but this mail came back, which is
        // what a fetch dying between the insert and the update looks like.
        if (! $reply->wasRecentlyCreated) {
            return false;
        }

        // A machine answered: never pause, never pay for a model call, and the
        // classification is already known.
        if ($mail->isAutoReply) {
            $reply->update(['classification' => ReplyClassification::AutoReply]);

            return true;
        }

        $this->pause($reply);

        // The net under the agent. It runs anyway: "stop, and send it to my
        // colleague instead" needs both this and a reading of the rest.
        if ($this->optOut->found($mail->body, $mail->subject)) {
            $this->currentProject->run(
                $reply->lead->project,
                fn () => $this->outcomes->suppress($reply, 'replied with an explicit opt-out'),
            );
        }

        HandleReply::dispatch($reply);

        return true;
    }

    /**
     * A bounce that came back by mail rather than during the SMTP conversation.
     *
     * Same treatment as an immediate 5xx: a hard failure suppresses the address
     * against THIS mailbox and stops the sequence, because retrying a known-dead
     * address is what costs a domain its reputation. A soft failure — a full
     * mailbox, a server having a bad afternoon — decides nothing and waits a day.
     */
    private function recordBounce(EmailAccount $account, BounceReport $bounce): bool
    {
        $sent = Message::query()
            ->where('email_account_id', $account->id)
            ->where('direction', MessageDirection::Outbound)
            ->when(
                $bounce->originalMessageId !== null,
                fn (Builder $query) => $query->where('message_id', $bounce->originalMessageId),
                // No usable original id, so fall back to the most recent mail
                // sent to that address from this mailbox.
                fn (Builder $query) => $query->whereRelation(
                    'lead',
                    fn (Builder $lead) => $lead->whereRaw('lower(email) = ?', [$bounce->recipient]),
                )->latest('sent_at'),
            )
            ->with(['lead', 'campaignLead'])
            ->first();

        if ($sent === null) {
            return false;
        }

        if (! $bounce->isHard) {
            $sent->campaignLead?->update(['next_action_at' => now()->addDay()]);

            return false;
        }

        $sent->update(['status' => MessageStatus::Bounced]);

        $this->currentProject->run($sent->lead->project, function () use ($sent, $account, $bounce): void {
            $this->suppressions->recordBounce($sent->lead, $account, $bounce->diagnostic);

            $sent->lead->update(['status' => OutreachStatus::Suppressed]);

            $sent->campaignLead?->update([
                'status' => CampaignLeadStatus::Stopped,
                'pause_reason' => 'bounced',
                'next_action_at' => null,
            ]);
        });

        return true;
    }

    /**
     * Before anything decides anything. The next follow-up must not leave while
     * we are still working out what the answer meant.
     */
    private function pause(Message $reply): void
    {
        $reply->campaignLead?->update([
            'status' => CampaignLeadStatus::Paused,
            'paused_at' => now(),
            'pause_reason' => 'replied',
        ]);

        $reply->lead->update(['status' => OutreachStatus::Replied]);
    }
}
