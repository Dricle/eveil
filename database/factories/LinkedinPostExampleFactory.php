<?php

namespace Database\Factories;

use App\Enums\LinkedinPostExampleSource;
use App\Models\LinkedinPostExample;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LinkedinPostExample>
 */
class LinkedinPostExampleFactory extends Factory
{
    protected $model = LinkedinPostExample::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'body' => fake()->paragraph(),
            'source' => LinkedinPostExampleSource::Manual,
        ];
    }
}
