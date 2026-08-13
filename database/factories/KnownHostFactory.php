<?php

namespace Database\Factories;

use App\Enums\HostKind;
use App\Models\KnownHost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnownHost>
 */
class KnownHostFactory extends Factory
{
    protected $model = KnownHost::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'host' => fake()->unique()->domainName(),
            'kind' => HostKind::Entity,
            'last_verified_at' => now(),
        ];
    }

    public function index(): static
    {
        return $this->state(['kind' => HostKind::Index]);
    }

    public function stale(): static
    {
        return $this->state(['last_verified_at' => now()->subYear()]);
    }
}
