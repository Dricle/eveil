<?php

namespace App\Http\Resources;

use App\Models\CampaignLead;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One conversation as the inbox shows it: who, at which company, what was said,
 * and what the agent decided about the last thing they said.
 *
 * @mixin CampaignLead
 */
class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lastInbound = $this->messages->last(fn (Message $message): bool => $message->direction->isInbound());
        $lastOutbound = $this->messages->last(fn (Message $message): bool => ! $message->direction->isInbound());

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'pause_reason' => $this->pause_reason,
            'campaign' => ['id' => $this->campaign->id, 'name' => $this->campaign->name],
            'lead' => [
                'id' => $this->lead->id,
                'name' => mb_trim($this->lead->first_name.' '.$this->lead->last_name) ?: null,
                'email' => $this->lead->email,
                'title' => $this->lead->title,
                'status' => $this->lead->status->value,
                'company' => $this->lead->company?->name,
            ],
            // What the agent made of the latest reply, which is what decides the
            // order of this list and whether anybody has to act.
            'classification' => $lastInbound?->classification?->value,
            'needs_attention' => $lastInbound?->classification?->needsAttention() ?? false,
            'replied_at' => $lastInbound?->received_at?->toIso8601String(),
            // What became of the last thing we sent. A send that was refused
            // still leaves a row, on purpose: the attempt is a fact worth
            // keeping. But a list that showed it exactly like a delivered mail
            // would tell somebody their mail went out when it never left.
            'sent_at' => $lastOutbound?->sent_at?->toIso8601String(),
            'delivery' => $lastOutbound?->status?->value,
            'messages' => $this->messages->map(fn (Message $message): array => [
                'id' => $message->id,
                'direction' => $message->direction->value,
                'subject' => $message->subject,
                'body' => $message->body,
                'classification' => $message->classification?->value,
                'status' => $message->status?->value,
                'at' => ($message->sent_at ?? $message->received_at ?? $message->created_at)?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
