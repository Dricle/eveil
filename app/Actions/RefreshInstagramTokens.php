<?php

namespace App\Actions;

use App\Enums\SocialPlatform;
use App\Models\SocialAccount;
use App\Services\Instagram\InstagramGraphApi;
use Throwable;

/**
 * Keeps every Meta app token alive: Instagram's last 60 days and can only be
 * refreshed while still valid and at least a day old, and one that lapses is
 * gone for good. Refreshed once it is past its first ten days, so a daily run
 * has fifty days of retries before anything is lost.
 *
 * A token that fails stays as it is, with the reason on the account: the
 * account falls back on Composio once it expires, if it has it.
 */
class RefreshInstagramTokens
{
    public function __construct(private InstagramGraphApi $graph) {}

    /**
     * How many tokens were refreshed.
     */
    public function handle(): int
    {
        $refreshed = 0;

        SocialAccount::query()
            ->where('platform', SocialPlatform::Instagram)
            ->whereNotNull('platform_app_id')
            ->whereNotNull('secret')
            ->where('secret_expires_at', '>', now())
            ->where('secret_expires_at', '<', now()->addDays(50))
            ->each(function (SocialAccount $account) use (&$refreshed): void {
                try {
                    $token = $this->graph->refresh($account->credential());
                } catch (Throwable $e) {
                    $account->update(['last_error' => "Your Meta app token could not be refreshed: {$e->getMessage()}"]);

                    return;
                }

                $account->update([
                    'secret' => $token['token'],
                    'secret_expires_at' => now()->addSeconds($token['expires_in']),
                    'last_error' => null,
                ]);

                $refreshed++;
            });

        return $refreshed;
    }
}
