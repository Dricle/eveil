<?php

namespace App\Services\Social;

use App\Models\SocialAccount;
use App\Models\SocialPost;
use Illuminate\Support\Collection;

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
     * Like counts for this network's published posts, keyed by post id, for
     * the posts it could read. The posts, not just their ids: reading one may
     * need the account it went out on.
     *
     * @param  Collection<int, SocialPost>  $posts
     * @return array<int, int>
     */
    public function likeCounts(Collection $posts): array;
}
