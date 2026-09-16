<?php

namespace App\Services\Linkedin;

use App\Enums\LinkedinAccountStatus;
use App\Models\LinkedinAccount;
use App\Support\LinkedinCredentials;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The calls this product needs against LinkedIn's official API: publish a
 * post as a member, refresh an expiring token, and (only for an account with
 * the separate, restricted stats connection) read a post's engagement.
 * Personal-profile only (`w_member_social`) - see `.ai/rules/linkedin.md`.
 *
 * Comment read/reply on your own post is a documented gap, not an oversight:
 * LinkedIn's exact API shape for it needs verifying against their current
 * developer docs before it is wired up.
 */
class LinkedinClient
{
    private const API_VERSION = '202509';

    public function __construct(private LinkedinCredentials $credentials) {}

    /**
     * Publishes as the connected member. Returns the created post's URN.
     */
    public function publishPost(LinkedinAccount $account, string $text): string
    {
        $response = Http::withToken($account->access_token)
            ->withHeaders($this->headers())
            ->post('https://api.linkedin.com/rest/posts', [
                'author' => $account->member_urn,
                'commentary' => $text,
                'visibility' => 'PUBLIC',
                'distribution' => [
                    'feedDistribution' => 'MAIN_FEED',
                    'targetEntities' => [],
                    'thirdPartyDistributionChannels' => [],
                ],
                'lifecycleState' => 'PUBLISHED',
                'isReshareDisabledByAuthor' => false,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("LinkedIn refused the post: HTTP {$response->status()} {$response->body()}");
        }

        $urn = $response->header('x-restli-id') ?: $response->header('x-linkedin-id');

        if ($urn === '') {
            throw new RuntimeException('LinkedIn published the post but returned no id.');
        }

        return $urn;
    }

    /**
     * The reaction/comment counts on a post the connected member published -
     * `App\Actions\FetchLinkedinPostStats`'s only call. Requires the
     * SEPARATE `stats_access_token` (the Community Management app's
     * restricted `r_member_social_feed`, not the posting app's
     * `w_member_social`): a 401/403 here almost always means the account
     * was never granted it, which is expected for most accounts and must
     * never be treated as an error to surface.
     */
    public function socialMetadata(LinkedinAccount $account, string $urn): int
    {
        $response = Http::withToken($account->stats_access_token)
            ->withHeaders($this->headers())
            ->get("https://api.linkedin.com/rest/socialMetadata/{$urn}");

        if (! $response->successful()) {
            throw new RuntimeException("LinkedIn refused the social metadata request: HTTP {$response->status()}");
        }

        /** @var array<string, array{count: int}> $reactions */
        $reactions = $response->json('reactionSummaries') ?? [];

        return array_sum(array_column($reactions, 'count'));
    }

    /**
     * Exchanges a refresh token for a new access token, called lazily when a
     * call 401s rather than on a schedule - the same "last moment before the
     * call" reasoning as `ProviderCredentials::apply()`.
     */
    public function refreshToken(LinkedinAccount $account): void
    {
        if ($account->refresh_token === null) {
            $account->update(['status' => LinkedinAccountStatus::Expired, 'last_error' => 'No refresh token stored: reconnect the account.']);

            return;
        }

        $response = Http::asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->refresh_token,
            'client_id' => $this->credentials->clientId(),
            'client_secret' => $this->credentials->clientSecret(),
        ]);

        if (! $response->successful()) {
            $account->update([
                'status' => LinkedinAccountStatus::Error,
                'last_error' => "Token refresh failed: HTTP {$response->status()}",
            ]);

            return;
        }

        $account->update([
            'access_token' => (string) $response->json('access_token'),
            'access_token_expires_at' => now()->addSeconds((int) $response->json('expires_in')),
            'refresh_token' => $response->json('refresh_token') ?? $account->refresh_token,
            'status' => LinkedinAccountStatus::Active,
            'last_error' => null,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'LinkedIn-Version' => self::API_VERSION,
            'X-Restli-Protocol-Version' => '2.0.0',
        ];
    }
}
