<?php

namespace App\Actions;

use App\Models\PlatformApp;

/**
 * Removes an organization's own OAuth app. The Instagram accounts that
 * published through it lose their token from it (falling back on Composio
 * where they have it), rather than keeping a token from an app Eveil no
 * longer knows. An ad account keeps working on the instance's app, through
 * its own `platform_app_id` going null.
 */
class RemovePlatformApp
{
    public function __construct(private DropInstagramMetaConnection $dropMeta) {}

    public function handle(PlatformApp $app): void
    {
        $app->socialAccounts()->each(fn ($account) => $this->dropMeta->handle($account));

        $app->delete();
    }
}
