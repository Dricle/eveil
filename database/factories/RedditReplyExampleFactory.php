<?php

namespace Database\Factories;

use App\Enums\RedditReplyExampleSource;
use App\Models\RedditReplyExample;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RedditReplyExample>
 */
class RedditReplyExampleFactory extends Factory
{
    protected $model = RedditReplyExample::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'body' => fake()->paragraph(),
            'source' => RedditReplyExampleSource::Manual,
        ];
    }
}
