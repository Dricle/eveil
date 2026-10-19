<?php

namespace Database\Factories;

use App\Models\Ad;
use App\Models\AdMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdMetric>
 */
class AdMetricFactory extends Factory
{
    protected $model = AdMetric::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ad_id' => Ad::factory(),
            'date' => now()->toDateString(),
            'impressions' => 1000,
            'clicks' => 40,
            'cost_micros' => 20_000_000,
            'conversions' => 2,
            'conversion_value' => 0,
        ];
    }
}
