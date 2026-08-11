<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'domain' => fake()->unique()->domainName(),
            'name' => fake()->company(),
            'website' => fake()->url(),
            'language' => 'fr',
            'source' => 'web_search',
            'discovered_at' => now(),
        ];
    }
}
