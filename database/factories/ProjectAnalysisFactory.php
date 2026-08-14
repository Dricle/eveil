<?php

namespace Database\Factories;

use App\Enums\AnalysisStatus;
use App\Enums\AnalysisType;
use App\Models\Project;
use App\Models\ProjectAnalysis;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectAnalysis>
 */
class ProjectAnalysisFactory extends Factory
{
    protected $model = ProjectAnalysis::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'type' => AnalysisType::Website,
            'status' => AnalysisStatus::Succeeded,
            'summary' => ['what' => fake()->sentence(), 'for_whom' => fake()->sentence()],
        ];
    }
}
