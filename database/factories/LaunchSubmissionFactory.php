<?php

namespace Database\Factories;

use App\Models\LaunchItem;
use App\Models\LaunchSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LaunchSubmission>
 */
class LaunchSubmissionFactory extends Factory
{
    protected $model = LaunchSubmission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'launch_item_id' => LaunchItem::factory(),
            'url' => fake()->url(),
            'posted_at' => now(),
        ];
    }
}
