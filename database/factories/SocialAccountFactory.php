<?php

namespace Database\Factories;

use App\Enums\SocialAccountStatus;
use App\Enums\SocialPlatform;
use App\Models\Organization;
use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    protected $model = SocialAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'platform' => SocialPlatform::Bluesky,
            'handle' => fake()->unique()->userName().'.bsky.social',
            'external_id' => 'did:plc:'.fake()->unique()->regexify('[a-z2-7]{24}'),
            'display_name' => fake()->name(),
            'secret' => 'abcd-efgh-ijkl-mnop',
            'status' => SocialAccountStatus::Active,
        ];
    }

    /**
     * A LinkedIn member profile: no handle, a member URN, an OAuth token.
     */
    public function linkedin(): static
    {
        return $this->state(fn (): array => [
            'platform' => SocialPlatform::Linkedin,
            'handle' => null,
            'external_id' => 'urn:li:person:'.fake()->unique()->regexify('[A-Za-z0-9_-]{16}'),
            'secret' => fake()->sha256(),
            'refresh_secret' => fake()->sha256(),
            'secret_expires_at' => now()->addDays(60),
            'refresh_secret_expires_at' => now()->addYear(),
        ]);
    }
}
