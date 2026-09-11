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
            // What the agent made of the latest reply. A permanent record of
            // what it WAS, so it stays on screen (in a neutral color once
            // resolved) even after `needs_attention` clears - unlike that
            // flag, this one never goes away or gets a color back.
            'classification' => $lastInbound?->classification?->value,
            // Whether a person still has to look at this one. `resolved` is
            // the actual stored state - set by the user's own toggle, by a
            // status change (`SetOutreachStatus`), or cleared the moment a
            // new reply arrives (`FetchReplies::pause()`). `needs_attention`
            // is `CampaignLead::needsAttention()` - the SAME definition
            // `InboxController` sums for a folder's badge count, so the two
            // can never quietly disagree.
            'resolved' => $this->attention_resolved_at !== null,
            'needs_attention' => $this->needsAttention(),
            'replied_at' => $lastInbound?->received_at?->toIso8601String(),
            // What became of the last thing we sent. A send that was refused
            // still leaves a row, on purpose: the attempt is a fact worth
            // keeping. But a list that showed it exactly like a delivered mail
            // would tell somebody their mail went out when it never left.
            'sent_at' => $lastOutbound?->sent_at?->toIso8601String(),
            'delivery' => $lastOutbound?->status?->value,
            // Newest reply on top, like the thread list itself.
            'messages' => $this->messages->reverse()->map(fn (Message $message): array => [
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
