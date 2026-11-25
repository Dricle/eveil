<?php

namespace Database\Factories;

use App\Enums\AdPlatform;
use App\Models\AdConversion;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdConversion>
 */
class AdConversionFactory extends Factory
{
    protected $model = AdConversion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'platform' => AdPlatform::GoogleAds,
            'click_id' => fake()->unique()->regexify('[A-Za-z0-9_-]{40}'),
            'occurred_at' => now(),
        ];
    }
}
