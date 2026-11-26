<?php

namespace Database\Factories;

use App\Models\LaunchItem;
use App\Models\LaunchVenue;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LaunchItem>
 */
class LaunchItemFactory extends Factory
{
    protected $model = LaunchItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'launch_venue_id' => LaunchVenue::factory(),
        ];
    }
}
