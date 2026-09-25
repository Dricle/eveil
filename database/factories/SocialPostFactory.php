<?php

namespace Database\Factories;

use App\Enums\SocialPlatform;
use App\Enums\SocialPostSourceType;
use App\Enums\SocialPostStatus;
use App\Models\Project;
use App\Models\SocialPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialPost>
 */
class SocialPostFactory extends Factory
{
    protected $model = SocialPost::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'platform' => SocialPlatform::Bluesky,
            'source_type' => SocialPostSourceType::KnowledgeBase,
            'evidence' => fake()->sentence(),
            'body' => fake()->sentence(),
            'status' => SocialPostStatus::Draft,
        ];
    }

    public function x(): static
    {
        return $this->state(['platform' => SocialPlatform::X]);
    }
}
