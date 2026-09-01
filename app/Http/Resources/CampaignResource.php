<?php

namespace App\Http\Resources;

use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin Campaign
 */
class CampaignResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'target_profile' => $this->whenLoaded('targetProfile', fn () => [
                'id' => $this->targetProfile?->id,
                'name' => $this->targetProfile?->name,
                'type' => $this->targetProfile?->type,
            ]),
            'steps' => CampaignStepResource::collection($this->whenLoaded('steps')),
            'steps_count' => $this->whenCounted('steps'),
            // Both are aggregates the list query adds, and both are sent even
            // when they are empty. `whenCounted` / `whenNotNull` would drop the
            // key instead, and a missing key reaches the page as `undefined`,
            // which slips past a null check and lands in `new Date()` as
            // "Invalid Date".
            'live_leads_count' => (int) ($this->live_leads_count ?? 0),
            // The dashboard's three: everyone ever enrolled, and how many of
            // them got at least one message each way. Same "always sent" rule
            // as `live_leads_count`, and absent entirely on lists that never
            // added the aggregates, `?? 0` covers it identically.
            'leads_count' => (int) ($this->campaign_leads_count ?? 0),
            'sent_count' => (int) ($this->sent_leads_count ?? 0),
            'replies_count' => (int) ($this->replied_leads_count ?? 0),
            // Parsed because an aggregate arrives as a raw database string
            // while every other date here is a cast attribute.
            'next_action_at' => is_string($this->next_action_at ?? null)
                ? Carbon::parse($this->next_action_at)
                : null,
            'updated_at' => $this->updated_at,
        ];
    }
}
