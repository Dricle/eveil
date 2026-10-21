<?php

namespace App\Actions;

use App\Enums\SocialAccountStatus;
use App\Enums\SocialPlatform;
use App\Models\PlatformApp;
use App\Models\Project;
use App\Models\SocialAccount;
use App\Services\Instagram\InstagramGraphApi;

/**
 * Records an Instagram account authorized through the organization's own
 * Meta app. Matched on the professional account's own id, so an account
 * already connected through Composio gains its Meta app token beside its
 * Composio connection: it publishes through the Meta app from now on, and
 * Composio stays as the fallback.
 */
class ConnectInstagramThroughOwnApp
{
    public function __construct(private InstagramGraphApi $graph) {}

    /**
     * @param  array{token: string, expires_in: int}  $token
     */
    public function handle(PlatformApp $app, Project $project, array $token): string
    {
        $profile = $this->graph->profile($token['token']);

        $account = SocialAccount::query()->firstOrNew([
            'organization_id' => $project->organization_id,
            'platform' => SocialPlatform::Instagram,
            'external_id' => $profile['id'],
        ]);

        $hadComposio = $account->composio_connection_id !== null;

        $account->fill([
            'platform_app_id' => $app->id,
            'handle' => $profile['username'],
            'display_name' => $profile['name'],
            'secret' => $token['token'],
            'secret_expires_at' => now()->addSeconds($token['expires_in']),
            'status' => SocialAccountStatus::Active,
            'last_error' => null,
        ])->save();

        $account->projects()->syncWithoutDetaching([$project->id]);

        return $hadComposio
            ? "Connected @{$account->handle} through your Meta app: it now publishes through it, ahead of Composio, which stays as a fallback."
            : "Connected @{$account->handle} through your Meta app.";
    }
}
