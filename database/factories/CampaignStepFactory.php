<?php

namespace Database\Factories;

use App\Enums\CampaignStepType;
use App\Models\Campaign;
use App\Models\CampaignStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignStep>
 */
class CampaignStepFactory extends Factory
{
    protected $model = CampaignStep::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'position' => 1,
            'type' => CampaignStepType::Email,
        ];
    }
}
