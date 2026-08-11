<?php

namespace Database\Factories;

use App\Enums\IcpSource;
use App\Models\Icp;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Icp>
 */
class IcpFactory extends Factory
{
    protected $model = Icp::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->words(3, true),
            'criteria' => [
                'sectors' => ['web agencies'],
                'geography' => ['BE'],
                'titles' => ['founder'],
            ],
            'source' => IcpSource::Agent,
            'is_active' => true,
        ];
    }
}
