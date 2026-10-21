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
 * asked. All of them, not a pick: a new account starts with a zero budget,
 * which means Eveil spends nothing there until a person sets one, so listing
 * an extra account costs nothing and choosing happens by giving it money.
 *
 * Reconnecting refreshes the tokens of accounts already there and keeps
 * their budgets.
 */
class ConnectAdAccounts
{
    /**
     * How many accounts the grant reached.
     */
    public function handle(Project $project, AdPlatform $platform, PlatformApp $app, AdTokens $tokens): int
    {
        $accounts = $platform->client()->accounts($app, $tokens);

        $accounts->each(fn (AdAccountData $data): AdAccount => AdAccount::query()->updateOrCreate(
            ['project_id' => $project->id, 'platform' => $platform, 'external_id' => $data->externalId],
            [
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
            ],
        ));

        return $accounts->count();
    }
}
