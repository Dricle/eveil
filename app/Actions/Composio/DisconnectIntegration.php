<?php

namespace App\Actions\Composio;

use App\Models\Integration;
use App\Services\Composio\ComposioClient;
use Throwable;

/**
 * Drops a Composio connection: revoked on Composio's side, then the row.
 * What hangs off it goes too, unless it has another way in: an Instagram
 * account also connected through the organization's own Meta app stays, on
 * that app alone. Disconnecting in Eveil must not leave a working token
 * behind.
 */
class DisconnectIntegration
{
    public function __construct(private ComposioClient $composio) {}

    public function handle(Integration $integration): void
    {
        try {
            $this->composio->deleteConnection($integration);
        } catch (Throwable) {
            // Already gone on Composio's side, or unreachable: the row goes
            // either way, and the user can still revoke from the service.
        }

        $integration->socialAccounts()->whereNull('platform_app_id')->delete();
        $integration->delete();
    }
}
