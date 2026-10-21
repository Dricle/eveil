<?php

namespace App\Enums;

use App\Actions\Composio\ConnectInstagramAccount;
use App\Models\Integration;
use App\Models\Project;

/**
 * The services an organization can connect through Composio. Adding one is a
 * case here: its Composio toolkit slug, what Eveil does once it is connected,
 * and the screen the user returns to. The connection flow itself
 * (`ComposioOAuthController`) and the API (`ComposioClient`) are shared.
 */
enum ComposioToolkit: string
{
    case Instagram = 'instagram';

    public function label(): string
    {
        return match ($this) {
            self::Instagram => 'Instagram',
        };
    }

    /**
     * Turns a freshly authorized connection into what this service is for in
     * Eveil, and returns a sentence for the user. Throws when the service
     * cannot be used after all; the caller then drops the connection.
     */
    public function connected(Integration $integration, Project $project): string
    {
        return match ($this) {
            self::Instagram => app(ConnectInstagramAccount::class)->handle($integration, $project),
        };
    }

    /**
     * The named route the user comes back to, inside the project they
     * connected from.
     */
    public function settingsRoute(): string
    {
        return match ($this) {
            self::Instagram => 'settings.instagram.index',
        };
    }
}
