<?php

namespace Database\Factories;

use App\Enums\AgentRunStatus;
use App\Enums\AgentType;
use App\Models\AgentRun;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentRun>
 */
class AgentRunFactory extends Factory
{
    protected $model = AgentRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'type' => AgentType::Planner,
            'status' => AgentRunStatus::Succeeded,
            'tokens_in' => fake()->numberBetween(100, 10000),
            'tokens_out' => fake()->numberBetween(50, 2000),
            'cost' => fake()->randomFloat(6, 0, 1),
            'duration_ms' => fake()->numberBetween(200, 30000),
        ];
    }
}
