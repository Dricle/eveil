<?php

namespace App\Actions;

use App\Enums\SocialAccountStatus;
use App\Enums\SocialPlatform;
use App\Models\Integration;
use App\Models\Project;
use App\Models\SocialAccount;
use App\Services\Instagram\InstagramClient;

/**
 * What an Instagram connection becomes in Eveil: the account it authorized,
 * as a `SocialAccount` going through that connection, granted to the project
 * it was connected from.
 *
 * Connecting the same Instagram account again moves it onto the new
 * connection and drops the old one, so there is never a second live token
 * for one account.
 */
class ConnectInstagramAccount
{
    public function __construct(private InstagramClient $instagram, private DisconnectIntegration $disconnect) {}

    public function handle(Integration $integration, Project $project): string
    {
        $profile = $this->instagram->profile($integration);

        $account = SocialAccount::query()->firstOrNew([
            'organization_id' => $integration->organization_id,
            'platform' => SocialPlatform::Instagram,
            'external_id' => $profile['id'],
        ]);

        $previous = $account->integration;

        $account->fill([
            'integration_id' => $integration->id,
            'handle' => $profile['username'],
            'display_name' => $profile['name'],
            'status' => SocialAccountStatus::Active,
            'last_error' => null,
        ])->save();

        // Moved first, dropped after: the account hangs off its connection,
        // and dropping the old one while it still pointed there would take
        // the account with it.
        if ($previous !== null && $previous->isNot($integration)) {
            $this->disconnect->handle($previous);
        }

        $account->projects()->syncWithoutDetaching([$project->id]);

        return "Connected @{$account->handle}.";
    }
}
