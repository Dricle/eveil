<?php

namespace Database\Factories;

use App\Models\Erasure;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Erasure>
 */
class ErasureFactory extends Factory
{
    protected $model = Erasure::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'email_hash' => Erasure::hashFor(fake()->unique()->safeEmail()),
            'requested_at' => now(),
        ];
    }
}
