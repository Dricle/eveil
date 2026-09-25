<?php

namespace App\Services\Social;

use App\Models\SocialAccount;

/**
 * What the network-agnostic actions (`PublishSocialPost`,
 * `FetchSocialPostStats`) need from a network. Resolved from a post's
 * platform by `SocialPlatform::client()`. Connecting an account stays on
 * `BlueskyClient` itself: it is the only network with accounts.
 */
interface SocialClientInterface
{
    /**
     * Publishes as the account. Null when this network is not published
     * through its API, and nothing was sent.
     *
     * @return array{uri: string, url: string}|null
     */
    public function publish(SocialAccount $account, string $text, ?string $language = null): ?array;

    /**
     * Like counts by post id, for the ids this network could read.
     *
     * @param  array<int, string>  $ids
     * @return array<string, int>
     */
    public function likeCounts(array $ids): array;
}
