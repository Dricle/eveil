<?php

namespace App\Services\Ads;

use App\Models\AdAccount;
use Carbon\CarbonImmutable;

/**
 * A usable access token for an account, refreshed through its own network's
 * driver when the stored one is about to expire. Network-agnostic: what a
 * refresh looks like is the driver's business, keeping the new token is ours.
 */
class AdAccessTokens
{
    public function for(AdAccount $account): string
    {
        // A minute of margin: a token expiring mid-request fails the call.
        if ($account->token_expires_at === null || $account->token_expires_at->isAfter(now()->addMinute())) {
            return $account->access_token;
        }

        if ($account->refresh_token === null || $account->platformApp === null) {
            throw AdPlatformException::revoked("{$account->platform->label()} access expired. Connect the account again.");
        }

        $tokens = $account->platform->client()->refreshTokens($account->platformApp, $account->refresh_token);

        $account->update([
            'access_token' => $tokens->accessToken,
            // Some networks rotate the refresh token, most keep it.
            'refresh_token' => $tokens->refreshToken ?? $account->refresh_token,
            'token_expires_at' => $tokens->expiresAt ?? CarbonImmutable::now()->addHour(),
        ]);

        return $tokens->accessToken;
    }
}
