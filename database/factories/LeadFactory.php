<?php

namespace Database\Factories;

use App\Enums\EmailSource;
use App\Enums\EmailStatus;
use App\Enums\OutreachStatus;
use App\Models\Lead;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_status' => EmailStatus::Valid,
            'email_source' => EmailSource::Scraped,
            'source' => 'web_search',
            'discovered_at' => now(),
            'status' => OutreachStatus::New,
        ];
    }
}
