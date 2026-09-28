<?php

namespace App\Services\Linkedin;

use App\Enums\SocialAccountStatus;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Services\Social\SocialClientInterface;
use App\Support\LinkedinCredentials;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * LinkedIn's driver, on its official API: publish a post as a member,
 * refresh an expiring token, and (only for an account with the separate,
 * restricted stats connection) read a post's engagement. Personal-profile
 * only (`w_member_social`).
 *
 * On a LinkedIn `SocialAccount`, `secret` is the posting app's access token
 * and `stats_secret` the performance-polling app's.
 */
class LinkedinClient implements SocialClientInterface
{
    private const API_VERSION = '202608';

    public function __construct(private LinkedinCredentials $credentials) {}

    /**
     * Publishes as the connected member. The URI is the post's URN.
     *
     * @return array{uri: string, url: string}
     */
    public function publish(SocialAccount $account, string $text, ?string $language = null): ?array
    {
        $response = Http::withToken($account->secret)
            ->withHeaders($this->headers())
            ->post('https://api.linkedin.com/rest/posts', [
                'author' => $account->external_id,
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

        return ['uri' => $urn, 'url' => "https://www.linkedin.com/feed/update/{$urn}/"];
    }

    /**
     * Reaction counts, per post, through the SEPARATE stats token (the
     * Community Management app's restricted `r_member_social_feed`). A post
     * whose account never made that second connection is skipped outright,
     * expected for most accounts; one LinkedIn refuses is skipped too.
     *
     * @param  Collection<int, SocialPost>  $posts
     * @return array<int, int>
     */
    public function likeCounts(Collection $posts): array
    {
        $counts = [];

        foreach ($posts as $post) {
            $token = $post->socialAccount?->stats_secret;

            if ($token === null) {
                continue;
            }

            try {
                $response = Http::withToken($token)
                    ->withHeaders($this->headers())
                    ->get("https://api.linkedin.com/rest/socialMetadata/{$post->external_id}");
            } catch (Throwable) {
                continue;
            }

            if (! $response->successful()) {
                continue;
            }

            /** @var array<string, array{count: int}> $reactions */
            $reactions = $response->json('reactionSummaries') ?? [];

            $counts[$post->id] = array_sum(array_column($reactions, 'count'));
        }

        return $counts;
    }

    /**
     * Exchanges a refresh token for a new access token, called lazily when a
     * call 401s rather than on a schedule - the same "last moment before the
     * call" reasoning as `ProviderCredentials::apply()`.
     */
    public function refreshToken(SocialAccount $account): void
    {
        if ($account->refresh_secret === null) {
            $account->update(['status' => SocialAccountStatus::Expired, 'last_error' => 'No refresh token stored: reconnect the account.']);

            return;
        }

        $response = Http::asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->refresh_secret,
            'client_id' => $this->credentials->clientId(),
            'client_secret' => $this->credentials->clientSecret(),
        ]);

        if (! $response->successful()) {
            $account->update([
                'status' => SocialAccountStatus::Error,
                'last_error' => "Token refresh failed: HTTP {$response->status()}",
            ]);

            return;
        }

        $account->update([
            'secret' => (string) $response->json('access_token'),
            'secret_expires_at' => now()->addSeconds((int) $response->json('expires_in')),
            'refresh_secret' => $response->json('refresh_token') ?? $account->refresh_secret,
            'status' => SocialAccountStatus::Active,
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
