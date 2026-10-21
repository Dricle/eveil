<?php

namespace App\Actions\Composio;

use App\Models\Organization;

/**
 * Sets, replaces or removes an organization's Composio key. A connection
 * only exists in the Composio project of the key that made it, so a
 * different key (or none) leaves every current connection dead: they are
 * dropped here, revoked with the OLD key while it still works, rather than
 * left to fail at the next publish. Saving the same key again changes
 * nothing.
 */
class ChangeComposioKey
{
    public function __construct(private DisconnectComposioConnection $disconnect) {}

    public function handle(Organization $organization, ?string $apiKey): void
    {
        if ($apiKey !== null && $apiKey === $organization->composio_api_key) {
            return;
        }

        $organization->composioConnections()->each(fn ($connection) => $this->disconnect->handle($connection));

        $organization->update(['composio_api_key' => $apiKey, 'composio_auth_configs' => null]);
    }
}
