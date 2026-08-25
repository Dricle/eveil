<?php

namespace Database\Factories;

use App\Cloud\Models\CreditPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditPrice>
 */
class CreditPriceFactory extends Factory
{
    protected $model = CreditPrice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent' => fake()->unique()->slug(2),
            'credits' => fake()->numberBetween(1, 500),
            'effective_from' => now()->subDay(),
        ];
    }
}
