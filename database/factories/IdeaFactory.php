<?php

namespace Database\Factories;

use App\Enums\IdeaKind;
use App\Enums\IdeaStatus;
use App\Models\Idea;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Idea>
 */
class IdeaFactory extends Factory
{
    protected $model = Idea::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'kind' => IdeaKind::Article,
            'source' => 'reddit',
            'source_ref' => 'https://www.reddit.com/r/'.fake()->word().'/comments/'.fake()->unique()->bothify('??####').'/',
            'title' => fake()->sentence(),
            'angle' => fake()->sentence(),
            'status' => IdeaStatus::Open,
        ];
    }
}
