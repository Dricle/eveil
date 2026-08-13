<?php

namespace Database\Factories;

use App\Enums\PathHintKind;
use App\Models\PathHint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PathHint>
 */
class PathHintFactory extends Factory
{
    protected $model = PathHint::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => PathHintKind::Contact,
            'token' => fake()->unique()->slug(1),
        ];
    }
}
