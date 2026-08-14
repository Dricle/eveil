<?php

namespace Database\Factories;

use App\Enums\AutonomyLevel;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->company(),
            'url' => fake()->url(),
            'autonomy_level' => AutonomyLevel::SemiAuto,
        ];
    }
}
