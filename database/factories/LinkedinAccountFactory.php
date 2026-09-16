<?php

namespace Database\Factories;

use App\Enums\LinkedinAccountStatus;
use App\Models\LinkedinAccount;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LinkedinAccount>
 */
class LinkedinAccountFactory extends Factory
{
    protected $model = LinkedinAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'member_urn' => 'urn:li:person:'.fake()->unique()->regexify('[A-Za-z0-9_-]{16}'),
            'display_name' => fake()->name(),
            'access_token' => fake()->sha256(),
            'refresh_token' => fake()->sha256(),
            'access_token_expires_at' => now()->addDays(60),
            'refresh_token_expires_at' => now()->addYear(),
            'status' => LinkedinAccountStatus::Active,
        ];
    }
}
