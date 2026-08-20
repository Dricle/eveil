<?php

namespace App\Http\Resources;

use App\Models\CampaignLead;
use App\Models\CompanyTargetEvaluation;
use App\Models\Lead;
use App\Models\Message;
use Illuminate\Http\Request;

/**
 * Everything known about one person, in one place: how their address was found
 * and what it is worth, which sequences they are in, and every mail either way.
 *
 * The company travels as a reference rather than being copied onto the person:
 * it is a deduplicated object of its own, and two contacts at one firm must not
 * disagree about what that firm is.
 *
 * @mixin Lead
 */
class ContactSheetResource extends ContactResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'last_contacted_at' => $this->last_contacted_at?->toIso8601String(),
            'won_at' => $this->won_at?->toIso8601String(),
            // Provenance, for the user's own audit and nothing else: it is never
            // injected into a mail.
            'source' => $this->source,
            'company_detail' => $this->company === null ? null : [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'domain' => $this->company->domain,
                'website' => $this->company->website,
                'industry' => $this->company->industry,
                'size' => $this->company->size,
                'location' => $this->company->location,
                'status' => $this->company->status->value,
                // Why the qualifier thought this company was worth writing to,
                // which is also the opening line of the first mail.
                'evaluations' => $this->company->evaluations
                    ->map(fn (CompanyTargetEvaluation $evaluation): array => [
                        'profile' => $evaluation->targetProfile?->name,
                        'fit_score' => $evaluation->fit_score,
                        'fit_reason' => $evaluation->fit_reason,
                    ])->values()->all(),
            ],
            'campaigns' => $this->campaignLeads->map(fn (CampaignLead $membership): array => [
                'id' => $membership->id,
                'campaign' => $membership->campaign->name,
                'status' => $membership->status->value,
                'pause_reason' => $membership->pause_reason,
                'step' => $membership->current_step_position,
                'next_action_at' => $membership->next_action_at?->toIso8601String(),
                'mailbox' => $membership->emailAccount?->from_email,
            ])->values()->all(),
            'messages' => $this->messages->map(fn (Message $message): array => [
                'id' => $message->id,
                'direction' => $message->direction->value,
                'subject' => $message->subject,
                'body' => $message->body,
                'status' => $message->status?->value,
                'classification' => $message->classification?->value,
                'at' => ($message->sent_at ?? $message->received_at ?? $message->created_at)?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
