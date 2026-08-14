<?php

namespace Database\Factories;

use App\Enums\CampaignLeadStatus;
use App\Models\Campaign;
use App\Models\CampaignLead;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignLead>
 */
class CampaignLeadFactory extends Factory
{
    protected $model = CampaignLead::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'lead_id' => Lead::factory(),
            'status' => CampaignLeadStatus::Pending,
        ];
    }
}
