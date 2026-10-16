<?php

namespace Database\Factories;

use App\Enums\AdPlatform;
use App\Models\PlatformApp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformApp>
 */
class PlatformAppFactory extends Factory
{
    protected $model = PlatformApp::class;

    /**
     * An instance app: no organization.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => null,
            'platform' => AdPlatform::GoogleAds,
            'client_id' => fake()->uuid().'.apps.googleusercontent.com',
            'client_secret' => fake()->sha256(),
        ];
    }
}
