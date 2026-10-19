<?php

namespace Database\Factories;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\AdCampaign;
use App\Models\AdCreative;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ad>
 */
class AdFactory extends Factory
{
    protected $model = Ad::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ad_campaign_id' => AdCampaign::factory(),
            'project_id' => fn (array $attributes): int => (int) AdCampaign::query()->whereKey($attributes['ad_campaign_id'])->value('project_id'),
            'ad_creative_id' => fn (array $attributes): int => AdCreative::factory()->create(['project_id' => $attributes['project_id']])->id,
            'status' => AdStatus::Draft,
        ];
    }

    /**
     * Running on its network.
     */
    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => AdStatus::Active,
            'external_id' => (string) fake()->unique()->randomNumber(9),
        ]);
    }
}
