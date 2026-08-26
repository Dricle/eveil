<?php

namespace App\Actions;

use App\Enums\CampaignLeadStatus;
use App\Enums\CampaignStepType;
use App\Enums\EmailAccountStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\OutreachStatus;
use App\Models\CampaignLead;
use App\Models\CampaignStep;
use App\Models\Message;
use App\Services\Outreach\Sender;
use App\Services\Outreach\SendFailure;
use App\Services\Outreach\SuppressionList;

/**
 * One lead, one step forward: personalise the next mail, run the pre-send
 * checks, send it through the pinned mailbox, and set up whatever comes next.
 *
 * The checks are repeated here even though enrolment already ran them, and that
 * repetition is the point: a STOP reply, a bounce or the user marking the
 * company as a client all land between enrolment and the third follow-up, and
 * this is the last moment anything can stop the mail. A suppression list read
 * "before the campaign" is not a suppression list.
 *
 * Wait steps are not sent, they are waited: the step's own delay decides when
 * the following mail becomes due, which is what makes the sequence a sequence
 * rather than a burst.
 */
class SendNextStep
{
    public function __construct(
        private PersonalizeMessage $personalize,
        private Sender $sender,
        private SuppressionList $suppressions,
    ) {}

    public function handle(CampaignLead $campaignLead): void
    {
        $lead = $campaignLead->lead;
        $account = $campaignLead->emailAccount;

        if ($account === null || $account->status !== EmailAccountStatus::Active) {
            $this->pause($campaignLead, 'mailbox_unavailable');

            return;
        }

        // Everything the user or the recipient has decided since enrolment.
        if (! $lead->isSendable() || $lead->status->isExcluded() || $this->suppressions->suppresses($lead, $account)) {
            $campaignLead->update([
                'status' => CampaignLeadStatus::Stopped,
                'pause_reason' => 'suppressed',
            ]);

            return;
        }

        $step = $this->nextStep($campaignLead);

        // Nothing left to send: the sequence has run its course, and the lead
        // stays contacted rather than being handed back to another campaign.
        if ($step === null) {
            $campaignLead->update([
                'status' => CampaignLeadStatus::Completed,
                'next_action_at' => null,
            ]);

            return;
        }

        if ($step->type === CampaignStepType::Wait) {
            $campaignLead->update([
                'current_step_position' => $step->position,
                'next_action_at' => now()->addHours($step->delay_hours ?? 24),
                'status' => CampaignLeadStatus::Running,
            ]);

            return;
        }

        $written = $this->personalize->handle($step, $lead);

        // Threading: a follow-up answers our own previous mail to this person,
        // so it lands in that conversation instead of arriving as a second
        // cold approach from the same sender.
        $previous = Message::query()
            ->where('campaign_lead_id', $campaignLead->id)
            ->where('direction', MessageDirection::Outbound)
            ->oldest('id')
            ->value('message_id');

        try {
            $messageId = $this->sender->send($account, $lead, $written['subject'], $written['body'], $previous);
        } catch (SendFailure $failure) {
            $this->handleFailure($campaignLead, $failure, $written);

            return;
        }

        Message::query()->create([
            'lead_id' => $lead->id,
            'campaign_lead_id' => $campaignLead->id,
            'email_account_id' => $account->id,
            'step_variant_id' => $written['step_variant_id'],
            'direction' => MessageDirection::Outbound,
            'message_id' => $messageId,
            'in_reply_to' => $previous,
            'subject' => $written['subject'],
            'body' => $written['body'],
            'status' => MessageStatus::Sent,
            'sent_at' => now(),
        ]);

        $campaignLead->update([
            'current_step_position' => $step->position,
            'status' => CampaignLeadStatus::Running,
            // The step after this one decides the pace. A mail followed by
            // nothing leaves the row due immediately, which is how the sequence
            // finds its own end on the next pass.
            'next_action_at' => now(),
        ]);

        $lead->update([
            'status' => OutreachStatus::Contacted,
            'last_contacted_at' => now(),
        ]);
    }

    /**
     * The lowest step above the one already done. Read from the position rather
     * than counted, so reordering the sequence mid-flight does not resend a
     * mail somebody already received.
     */
    private function nextStep(CampaignLead $campaignLead): ?CampaignStep
    {
        return $campaignLead->campaign->steps()
            ->where('position', '>', $campaignLead->current_step_position)
            ->orderBy('position')
            ->first();
    }

    /**
     * @param  array{subject: string, body: string, step_variant_id: int}  $written
     */
    private function handleFailure(CampaignLead $campaignLead, SendFailure $failure, array $written): void
    {
        $lead = $campaignLead->lead;
        $account = $campaignLead->emailAccount;

        Message::query()->create([
            'lead_id' => $lead->id,
            'campaign_lead_id' => $campaignLead->id,
            'email_account_id' => $account->id,
            'step_variant_id' => $written['step_variant_id'],
            'direction' => MessageDirection::Outbound,
            // Not sent, so it never gets a real one, but the column is unique
            // and the row is the record that this was attempted.
            'message_id' => 'failed-'.$campaignLead->id.'-'.$campaignLead->current_step_position.'-'.now()->timestamp,
            'subject' => $written['subject'],
            'body' => $written['body'],
            'status' => $failure->kind === 'recipient' ? MessageStatus::Bounced : MessageStatus::Failed,
            'sent_at' => $failure->kind === 'transient' ? null : now(),
        ]);

        match ($failure->kind) {
            // The address is dead. Suppressing it is not tidiness: retrying a
            // known-bad address is exactly what costs a domain its reputation.
            'recipient' => $this->suppress($campaignLead, $failure),

            // The mailbox is broken, not the address. Stop it before it fails
            // its way through every lead in the campaign.
            'auth' => $this->pauseMailbox($campaignLead, $failure),

            // Greylisting, a rate limit, a blip. Nothing has been decided.
            default => $campaignLead->update(['next_action_at' => now()->addHour()]),
        };
    }

    private function suppress(CampaignLead $campaignLead, SendFailure $failure): void
    {
        $this->suppressions->recordBounce($campaignLead->lead, $campaignLead->emailAccount, $failure->getMessage());

        $campaignLead->lead->update(['status' => OutreachStatus::Suppressed]);

        $campaignLead->update([
            'status' => CampaignLeadStatus::Stopped,
            'pause_reason' => 'bounced',
            'next_action_at' => null,
        ]);
    }

    private function pauseMailbox(CampaignLead $campaignLead, SendFailure $failure): void
    {
        $campaignLead->emailAccount->update([
            'status' => EmailAccountStatus::Error,
            'last_error' => $failure->getMessage(),
            'last_checked_at' => now(),
        ]);

        $this->pause($campaignLead, 'mailbox_unavailable');
    }

    private function pause(CampaignLead $campaignLead, string $reason): void
    {
        $campaignLead->update([
            'status' => CampaignLeadStatus::Paused,
            'paused_at' => now(),
            'pause_reason' => $reason,
        ]);
    }
}
