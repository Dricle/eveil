<?php

namespace Database\Factories;

use App\Models\CampaignLead;
use App\Models\Lead;
use App\Models\Meeting;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meeting>
 */
class MeetingFactory extends Factory
{
    protected $model = Meeting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'campaign_lead_id' => CampaignLead::factory(),
            'lead_id' => Lead::factory(),
            'uid' => fake()->unique()->uuid().'@example.com',
            'starts_at' => now()->addDays(3)->setTime(10, 0),
            'duration_minutes' => 30,
            'timezone' => 'Europe/Brussels',
        ];
    }
}
