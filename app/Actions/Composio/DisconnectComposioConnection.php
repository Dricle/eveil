<?php

namespace App\Actions\Composio;

use App\Models\ComposioConnection;
use App\Services\Composio\ComposioClient;
use Throwable;

/**
 * Drops a Composio connection: revoked on Composio's side, then the row.
 * What hangs off it goes too, unless it has another way in: an Instagram
 * account also connected through the organization's own Meta app stays, on
 * that app alone. Disconnecting in Eveil must not leave a working token
 * behind.
 */
class DisconnectComposioConnection
{
    public function __construct(private ComposioClient $composio) {}

    public function handle(ComposioConnection $connection): void
    {
        try {
            $this->composio->deleteConnection($connection);
        } catch (Throwable) {
            // Already gone on Composio's side, or unreachable: the row goes
            // either way, and the user can still revoke from the service.
        }

        $connection->socialAccounts()->whereNull('platform_app_id')->delete();
        $connection->delete();
    }
}
