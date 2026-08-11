<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyIcpEvaluation;
use App\Models\Icp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyIcpEvaluation>
 */
class CompanyIcpEvaluationFactory extends Factory
{
    protected $model = CompanyIcpEvaluation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'icp_id' => Icp::factory(),
            'fit_score' => fake()->numberBetween(0, 100),
            'fit_reason' => fake()->sentence(),
        ];
    }
}
