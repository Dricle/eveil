<?php

namespace App\Actions;

use App\Enums\AdAccountStatus;
use App\Enums\AdPlatform;
use App\Models\AdAccount;
use App\Models\PlatformApp;
use App\Models\Project;
use App\Services\Ads\AdAccountData;
use App\Services\Ads\AdTokens;

/**
 * Stores every ad account an OAuth grant can manage, on the project that
 * asked. All of them, not a pick: a new account starts switched off, so
 * Eveil spends nothing there until a person switches it on, and listing an
 * extra account costs nothing. When the grant reaches a single account,
 * that one is obviously meant and starts switched on.
 *
 * Reconnecting refreshes the tokens of accounts already there and keeps
 * whether they are switched on.
 */
class ConnectAdAccounts
{
    /**
     * How many accounts the grant reached.
     */
    public function handle(Project $project, AdPlatform $platform, PlatformApp $app, AdTokens $tokens): int
    {
        $accounts = $platform->client()->accounts($app, $tokens);

        $accounts->each(function (AdAccountData $data) use ($project, $platform, $app, $tokens, $accounts): void {
            $account = AdAccount::query()->firstOrNew(['project_id' => $project->id, 'platform' => $platform, 'external_id' => $data->externalId]);

            if (! $account->exists) {
                $account->enabled = $accounts->count() === 1;
            }

            $account->fill([
                'platform_app_id' => $app->id,
                'name' => $data->name,
                'currency' => $data->currency,
                'time_zone' => $data->timeZone,
                'external_ref' => $data->ref === [] ? null : $data->ref,
                'access_token' => $tokens->accessToken,
                'refresh_token' => $tokens->refreshToken,
                'token_expires_at' => $tokens->expiresAt,
                'status' => AdAccountStatus::Active,
                'last_error' => null,
            ])->save();
        });

        // The budget's currency, until a person picks another: the first
        // account's, so the one account most people have is usable at once.
        if ($project->ads_currency === null && $accounts->isNotEmpty()) {
            $project->update(['ads_currency' => $accounts->first()->currency]);
        }

        return $accounts->count();
    }
}
