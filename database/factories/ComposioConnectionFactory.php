<?php

namespace Database\Factories;

use App\Enums\ComposioConnectionStatus;
use App\Enums\ComposioToolkit;
use App\Models\ComposioConnection;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComposioConnection>
 */
class ComposioConnectionFactory extends Factory
{
    protected $model = ComposioConnection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'toolkit' => ComposioToolkit::Instagram,
            'connected_account_id' => 'ca_'.fake()->unique()->regexify('[A-Za-z0-9]{12}'),
            'status' => ComposioConnectionStatus::Active,
        ];
    }
}
