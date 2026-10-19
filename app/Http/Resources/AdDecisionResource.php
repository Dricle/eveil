<?php

namespace App\Http\Resources;

use App\Models\AdDecision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The campaign and ad it concerns are referenced by id: the page already
 * holds them, drafts included, so a launch waiting for approval shows its
 * copy from there.
 *
 * @mixin AdDecision
 */
class AdDecisionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'status' => $this->status->value,
            'campaign_id' => $this->ad_campaign_id,
            'ad_id' => $this->ad_id,
            'daily_budget' => isset($this->payload['daily_budget_micros']) ? $this->payload['daily_budget_micros'] / 1_000_000 : null,
            'reasoning' => $this->reasoning,
            'outcome' => $this->outcome,
            'by_agent' => $this->agent_run_id !== null,
            'decided_by' => $this->decider?->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
