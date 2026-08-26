<?php

namespace Database\Factories;

use App\Enums\EmailExampleSource;
use App\Models\EmailExample;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailExample>
 */
class EmailExampleFactory extends Factory
{
    protected $model = EmailExample::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject' => fake()->sentence(4),
            'body' => fake()->paragraphs(2, true),
            'source' => EmailExampleSource::Manual,
        ];
    }
}
