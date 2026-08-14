<?php

namespace Database\Factories;

use App\Enums\DiscoveryRunStatus;
use App\Models\DiscoveryRun;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscoveryRun>
 */
class DiscoveryRunFactory extends Factory
{
    protected $model = DiscoveryRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'status' => DiscoveryRunStatus::Pending,
            'budget' => ['max_tokens' => 500_000, 'max_pages' => 500, 'max_leads' => 100],
        ];
    }
}
