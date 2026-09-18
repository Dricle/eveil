<?php

namespace Database\Factories;

use App\Enums\RedditReplyAngle;
use App\Enums\RedditReplySource;
use App\Enums\RedditReplyStatus;
use App\Models\Project;
use App\Models\RedditReply;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RedditReply>
 */
class RedditReplyFactory extends Factory
{
    protected $model = RedditReply::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'subreddit' => 'selfhosted',
            'thread_permalink' => 'https://www.reddit.com/r/selfhosted/comments/'.fake()->lexify('???????').'/',
            'thread_title' => fake()->sentence(),
            'source' => RedditReplySource::SubredditScan,
            'angle' => RedditReplyAngle::ValueComment,
            'evidence' => fake()->sentence(),
            'body' => fake()->paragraph(),
            'status' => RedditReplyStatus::Draft,
        ];
    }
}
