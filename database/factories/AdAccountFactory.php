<?php

namespace Database\Factories;

use App\Enums\AdAccountStatus;
use App\Enums\AdPlatform;
use App\Models\AdAccount;
use App\Models\PlatformApp;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdAccount>
 */
class AdAccountFactory extends Factory
{
    protected $model = AdAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory()->withAdsBudget(),
            'platform' => AdPlatform::GoogleAds,
            // The instance has one app per network: share it.
            'platform_app_id' => fn (): int => PlatformApp::query()->whereNull('organization_id')->where('platform', AdPlatform::GoogleAds)->value('id')
                ?? PlatformApp::factory()->create()->id,
            'external_id' => (string) fake()->unique()->numberBetween(1_000_000_000, 9_999_999_999),
            'name' => fake()->company(),
            'currency' => 'EUR',
            'time_zone' => 'Europe/Paris',
            'access_token' => fake()->sha256(),
            'refresh_token' => fake()->sha256(),
            'token_expires_at' => now()->addHour(),
            'enabled' => true,
            'status' => AdAccountStatus::Active,
        ];
    }
}
