<?php

namespace App\Http\Resources;

use App\Models\CampaignLead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One person's place in one sequence: which step they are on, when the next
 * mail is owed, and why nothing is owed when nothing is.
 *
 * @mixin CampaignLead
 */
class CampaignLeadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => trim("{$this->lead?->first_name} {$this->lead?->last_name}") ?: null,
            'email' => $this->lead?->email,
            'company' => $this->lead?->company?->name,
            'status' => $this->status,
            // The position of the last step DONE, so zero means enrolled and
            // not yet written to. Named for what it is: "step 0" on a screen
            // reads as a bug.
            'last_step' => $this->current_step_position,
            'next_action_at' => $this->next_action_at,
            'pause_reason' => $this->pause_reason,
            'sent' => $this->whenCounted('sentMessages'),
        ];
    }
}
