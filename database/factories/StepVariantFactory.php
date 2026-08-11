<?php

namespace Database\Factories;

use App\Models\CampaignStep;
use App\Models\StepVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StepVariant>
 */
class StepVariantFactory extends Factory
{
    protected $model = StepVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_step_id' => CampaignStep::factory(),
            'subject' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'weight' => 1,
        ];
    }
}
