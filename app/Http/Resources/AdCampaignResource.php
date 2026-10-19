<?php

namespace App\Http\Resources;

use App\Models\AdCampaign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AdCampaign
 */
class AdCampaignResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ad_account_id' => $this->ad_account_id,
            'name' => $this->name,
            'hypothesis' => $this->hypothesis,
            'targeting' => $this->targeting,
            'daily_budget' => $this->daily_budget_micros / 1_000_000,
            'currency' => $this->account->currency,
            'platform_label' => $this->account->platform->label(),
            'status' => $this->status->value,
            'launched_at' => $this->launched_at?->toIso8601String(),
            'ads' => AdResource::collection($this->whenLoaded('ads')),
        ];
    }
}
