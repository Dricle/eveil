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
}
