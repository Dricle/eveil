<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\RedditTriagedThread;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RedditTriagedThread>
 */
class RedditTriagedThreadFactory extends Factory
{
    protected $model = RedditTriagedThread::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'permalink' => 'https://www.reddit.com/r/selfhosted/comments/'.fake()->unique()->lexify('???????').'/',
            'triaged_at' => now(),
        ];
    }
}
