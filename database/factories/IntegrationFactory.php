<?php

namespace Database\Factories;

use App\Enums\ComposioToolkit;
use App\Enums\IntegrationStatus;
use App\Models\Integration;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Integration>
 */
class IntegrationFactory extends Factory
{
    protected $model = Integration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'toolkit' => ComposioToolkit::Instagram,
            'connected_account_id' => 'ca_'.fake()->unique()->regexify('[A-Za-z0-9]{12}'),
            'status' => IntegrationStatus::Active,
        ];
    }
}
