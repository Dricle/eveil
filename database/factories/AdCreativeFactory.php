<?php

namespace Database\Factories;

use App\Models\AdCreative;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdCreative>
 */
class AdCreativeFactory extends Factory
{
    protected $model = AdCreative::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'angle' => fake()->sentence(),
            'headlines' => ['Find clients on autopilot', 'Cold email that reads human', 'Start free today'],
            'descriptions' => ['Paste your URL, Eveil finds and writes to your next clients.', 'Self-hosted and open source.'],
            'final_url' => fake()->url(),
        ];
    }
}
