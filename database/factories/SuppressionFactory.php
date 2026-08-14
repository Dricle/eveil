<?php

namespace Database\Factories;

use App\Enums\SuppressionLayer;
use App\Models\Suppression;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Suppression>
 */
class SuppressionFactory extends Factory
{
    protected $model = Suppression::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'layer' => SuppressionLayer::Toxic,
            'email' => fake()->unique()->safeEmail(),
            'reason' => 'spam_trap',
        ];
    }
}
