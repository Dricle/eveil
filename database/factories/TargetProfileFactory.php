<?php

namespace Database\Factories;

use App\Enums\TargetProfileSource;
use App\Enums\TargetProfileType;
use App\Models\Project;
use App\Models\TargetProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TargetProfile>
 */
class TargetProfileFactory extends Factory
{
    protected $model = TargetProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->words(3, true),
            'type' => TargetProfileType::Customer,
            'criteria' => [
                'sectors' => ['web agencies'],
                'geography' => ['BE'],
                'titles' => ['founder'],
            ],
            'source' => TargetProfileSource::Agent,
            'is_active' => true,
        ];
    }
}
