<?php

namespace App\Actions;

use App\Models\SocialAccount;

/**
 * Stops an Instagram account publishing through the organization's own Meta
 * app: its token is forgotten. An account still connected through Composio
 * falls back on it; one with nothing left is removed.
 */
class DropInstagramMetaConnection
{
    public function handle(SocialAccount $account): void
    {
        if ($account->composio_connection_id === null) {
            $account->delete();

            return;
        }

        $account->update([
            'platform_app_id' => null,
            'secret' => null,
            'secret_expires_at' => null,
            'refresh_secret' => null,
            'refresh_secret_expires_at' => null,
        ]);
    }
}
