<?php

namespace Database\Factories;

use App\Enums\LinkedinPostSourceType;
use App\Enums\LinkedinPostStatus;
use App\Models\LinkedinPost;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LinkedinPost>
 */
class LinkedinPostFactory extends Factory
{
    protected $model = LinkedinPost::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'source_type' => LinkedinPostSourceType::KnowledgeBase,
            'evidence' => fake()->sentence(),
            'body' => fake()->paragraph(),
            'status' => LinkedinPostStatus::Draft,
        ];
    }
}
