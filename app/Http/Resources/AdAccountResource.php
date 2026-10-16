<?php

namespace App\Http\Resources;

use App\Models\AdAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * No tokens: write-only from the UI's point of view. Money in currency units.
 *
 * @mixin AdAccount
 */
class AdAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'platform' => $this->platform->value,
            'platform_label' => $this->platform->label(),
            'name' => $this->name,
            'external_id' => $this->external_id,
            'currency' => $this->currency,
            'monthly_budget' => $this->monthly_budget_micros / 1_000_000,
            'spent_this_month' => $this->spentThisMonthMicros() / 1_000_000,
            'active_daily_budget' => $this->activeDailyBudgetMicros() / 1_000_000,
            'has_conversion_tracking' => $this->has_conversion_tracking,
            'status' => $this->status->value,
            'last_error' => $this->last_error,
            'metrics_synced_at' => $this->metrics_synced_at?->toIso8601String(),
        ];
    }
}
