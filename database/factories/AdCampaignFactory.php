<?php

namespace Database\Factories;

use App\Enums\AdStatus;
use App\Models\AdAccount;
use App\Models\AdCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdCampaign>
 */
class AdCampaignFactory extends Factory
{
    protected $model = AdCampaign::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ad_account_id' => AdAccount::factory(),
            'project_id' => fn (array $attributes): int => (int) AdAccount::query()->whereKey($attributes['ad_account_id'])->value('project_id'),
            'name' => fake()->words(3, true),
            'hypothesis' => fake()->sentence(),
            'targeting' => ['keywords' => ['cold email tool'], 'countries' => ['FR'], 'languages' => ['fr']],
            'daily_budget_micros' => 10_000_000,
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
            'external_ref' => ['budget' => (string) fake()->randomNumber(9), 'ad_group' => (string) fake()->randomNumber(9)],
            'launched_at' => now()->subWeek(),
        ]);
    }
}
