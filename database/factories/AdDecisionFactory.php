<?php

namespace Database\Factories;

use App\Enums\AdDecisionStatus;
use App\Enums\AdDecisionType;
use App\Models\AdCampaign;
use App\Models\AdDecision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdDecision>
 */
class AdDecisionFactory extends Factory
{
    protected $model = AdDecision::class;

    /**
     * A campaign pause, the simplest decision there is.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ad_campaign_id' => AdCampaign::factory()->active(),
            'ad_account_id' => fn (array $attributes): int => (int) AdCampaign::query()->whereKey($attributes['ad_campaign_id'])->value('ad_account_id'),
            'project_id' => fn (array $attributes): int => (int) AdCampaign::query()->whereKey($attributes['ad_campaign_id'])->value('project_id'),
            'type' => AdDecisionType::PauseCampaign,
            'payload' => [],
            'reasoning' => fake()->sentence(),
            'status' => AdDecisionStatus::Proposed,
        ];
    }
}
