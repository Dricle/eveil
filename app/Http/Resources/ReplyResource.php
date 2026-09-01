<?php

namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One inbound reply, on its own rather than threaded into a conversation:
 * who, at which company, what the last line said, and what the agent made of
 * it. The lead-name assembly matches `ConversationResource`, which shapes the
 * same relation for the full thread the inbox screen reads.
 *
 * @mixin Message
 */
class ReplyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lead' => [
                'name' => mb_trim($this->lead->first_name.' '.$this->lead->last_name) ?: $this->lead->email,
                'company' => $this->lead->company?->name,
            ],
            'body' => $this->body,
            'classification' => $this->classification?->value,
            'at' => $this->received_at?->toIso8601String(),
        ];
    }
}
