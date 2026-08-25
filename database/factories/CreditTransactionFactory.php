<?php

namespace Database\Factories;

use App\Cloud\Models\CreditTransaction;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditTransaction>
 */
class CreditTransactionFactory extends Factory
{
    protected $model = CreditTransaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'type' => 'grant_trial',
            'credits' => 5000,
        ];
    }
}
