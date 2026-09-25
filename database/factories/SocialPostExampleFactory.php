<?php

namespace Database\Factories;

use App\Enums\SocialPlatform;
use App\Enums\SocialPostExampleSource;
use App\Models\SocialPostExample;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialPostExample>
 */
class SocialPostExampleFactory extends Factory
{
    protected $model = SocialPostExample::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'platform' => SocialPlatform::Bluesky,
            'body' => fake()->sentence(),
            'source' => SocialPostExampleSource::Manual,
        ];
    }
}
