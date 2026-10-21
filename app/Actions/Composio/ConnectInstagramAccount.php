<?php

namespace App\Actions\Composio;

use App\Enums\SocialAccountStatus;
use App\Enums\SocialPlatform;
use App\Models\ComposioConnection;
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
    public function __construct(private InstagramClient $instagram, private DisconnectComposioConnection $disconnect) {}

    public function handle(ComposioConnection $connection, Project $project): string
    {
        $profile = $this->instagram->profile($connection);

        $account = SocialAccount::query()->firstOrNew([
            'organization_id' => $connection->organization_id,
            'platform' => SocialPlatform::Instagram,
            'external_id' => $profile['id'],
        ]);

        $previous = $account->composioConnection;

        $account->fill([
            'composio_connection_id' => $connection->id,
            'handle' => $profile['username'],
            'display_name' => $profile['name'],
            'status' => SocialAccountStatus::Active,
            'last_error' => null,
        ])->save();

        // Moved first, dropped after: the account hangs off its connection,
        // and dropping the old one while it still pointed there would take
        // the account with it.
        if ($previous !== null && $previous->isNot($connection)) {
            $this->disconnect->handle($previous);
        }

        $account->projects()->syncWithoutDetaching([$project->id]);

        return "Connected @{$account->handle}.";
    }
}
