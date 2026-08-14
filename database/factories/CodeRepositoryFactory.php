<?php

namespace Database\Factories;

use App\Models\CodeRepository;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CodeRepository>
 */
class CodeRepositoryFactory extends Factory
{
    protected $model = CodeRepository::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->slug(2);

        return [
            'project_id' => Project::factory(),
            'url' => "https://github.com/dricle/{$name}",
            'name' => $name,
        ];
    }
}
