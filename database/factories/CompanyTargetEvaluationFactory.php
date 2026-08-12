<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyTargetEvaluation;
use App\Models\TargetProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyTargetEvaluation>
 */
class CompanyTargetEvaluationFactory extends Factory
{
    protected $model = CompanyTargetEvaluation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'target_profile_id' => TargetProfile::factory(),
            'fit_score' => fake()->numberBetween(0, 100),
            'fit_reason' => fake()->sentence(),
        ];
    }
}
