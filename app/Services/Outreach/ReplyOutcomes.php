<?php

namespace App\Services\Outreach;

use App\Actions\SetOutreachStatus;
use App\Enums\CampaignLeadStatus;
use App\Enums\OutreachStatus;
use App\Enums\ReplyClassification;
use App\Enums\SuppressionLayer;
use App\Models\Message;
use App\Models\Suppression;

/**
 * What can actually happen to a reply, as six operations the agent's tools call.
 *
 * The tools stay thin wrappers around these on purpose: everything here is
 * testable without a model, and the compliance-critical paths — suppression,
 * and an auto-reply resuming rather than pausing — must be provable without
 * asking a provider to please answer the same way twice.
 *
 * Each one records the verdict on the reply itself (`messages.classification`),
 * so the decision and its record are one act rather than a label written by one
 * pass and acted on by another.
 */
class ReplyOutcomes
{
    public function __construct(private SetOutreachStatus $setStatus) {}

    /**
     * They asked not to be written to again. The only opt-out channel there is,
     * so this errs toward suppressing everywhere it can.
     */
    public function suppress(Message $reply, string $reason = 'replied asking not to be contacted'): void
    {
        $lead = $reply->lead;

        if ($lead->email !== null) {
            $email = mb_strtolower($lead->email);

            Suppression::query()->create([
                'layer' => SuppressionLayer::OptOut,
                'project_id' => $lead->project_id,
                'organization_id' => $lead->project->organization_id,
                'email' => $email,
                'reason' => $reason,
                'source' => 'reply',
            ]);

            $this->escalateAcrossOrganization($lead->project->organization_id, $email, $reason);
        }

        $this->setStatus->forLead($lead, OutreachStatus::Suppressed);
        $this->stop($reply, 'unsubscribed');
        $this->record($reply, ReplyClassification::Unsubscribe);
    }

    /**
     * A clean no. The sequence ends; nothing else is sent to this person.
     */
    public function notInterested(Message $reply): void
    {
        $this->setStatus->forLead($reply->lead, OutreachStatus::Lost);
        $this->stop($reply, 'not_interested');
        $this->record($reply, ReplyClassification::NotInterested);
    }

    /**
     * Somebody has to write back themselves. Interest, a question, an answer
     * nobody can act on automatically — it stays paused and goes to the top of
     * the inbox rather than getting an automated reply it did not ask for.
     */
    public function needsHuman(Message $reply, bool $interested = false): void
    {
        $this->setStatus->forLead($reply->lead, OutreachStatus::Replied);

        $reply->campaignLead?->update([
            'status' => CampaignLeadStatus::Paused,
            'paused_at' => now(),
            'pause_reason' => 'awaiting_human',
        ]);

        $this->record($reply, $interested ? ReplyClassification::Interested : ReplyClassification::NeedsHuman);
    }

    /**
     * "Not now, ask me in the spring." The sequence keeps its place and comes
     * back later, which is worth more than a lead thrown away for saying so.
     */
    public function reschedule(Message $reply, int $months): void
    {
        $this->setStatus->forLead($reply->lead, OutreachStatus::Replied);

        $reply->campaignLead?->update([
            'status' => CampaignLeadStatus::Paused,
            'paused_at' => now(),
            'pause_reason' => 'not_now',
            // Held on the row itself: nothing polls a calendar, the dispatcher
            // simply finds it due again in N months.
            'next_action_at' => now()->addMonths($months),
        ]);

        $this->record($reply, ReplyClassification::NotNow);
    }

    /**
     * Wrong person. Their colleague may be the right one, but working out who is
     * a human's call in v0 — writing to somebody a stranger named is how a
     * mailbox earns a complaint.
     */
    public function wrongPerson(Message $reply): void
    {
        $this->setStatus->forLead($reply->lead, OutreachStatus::Replied);
        $this->stop($reply, 'wrong_person');
        $this->record($reply, ReplyClassification::WrongPerson);
    }

    /**
     * A machine answered. The sequence RESUMES: it was paused the moment the
     * mail was attributed, and a fortnight's holiday must not read as a reply.
     */
    public function ignore(Message $reply): void
    {
        $reply->campaignLead?->update([
            'status' => CampaignLeadStatus::Running,
            'paused_at' => null,
            'pause_reason' => null,
        ]);

        $this->record($reply, ReplyClassification::AutoReply);
    }

    /**
     * A second opt-out from the same address anywhere in the organization means
     * the person is telling several of its projects the same thing. It stops
     * being about one project at that point — there is no unsubscribe page to
     * click, so we widen the scope ourselves before they complain.
     */
    private function escalateAcrossOrganization(int $organizationId, string $email, string $reason): void
    {
        $projects = Suppression::query()
            ->where('layer', SuppressionLayer::OptOut)
            ->where('organization_id', $organizationId)
            ->where('email', $email)
            ->whereNotNull('project_id')
            ->distinct()
            ->count('project_id');

        if ($projects < 2) {
            return;
        }

        Suppression::query()->firstOrCreate([
            'layer' => SuppressionLayer::OptOut,
            'organization_id' => $organizationId,
            'project_id' => null,
            'email' => $email,
        ], [
            'reason' => $reason,
            'source' => 'escalated',
        ]);
    }

    private function stop(Message $reply, string $reason): void
    {
        $reply->campaignLead?->update([
            'status' => CampaignLeadStatus::Stopped,
            'pause_reason' => $reason,
            'next_action_at' => null,
        ]);
    }

    private function record(Message $reply, ReplyClassification $classification): void
    {
        $reply->update(['classification' => $classification]);
    }
}
