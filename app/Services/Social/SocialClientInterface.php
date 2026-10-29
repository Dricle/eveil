<?php

namespace App\Services\Social;

use App\Models\SocialAccount;
use App\Models\SocialPost;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * What the network-agnostic actions (`PublishSocialPost`,
 * `FetchSocialPostStats`) need from a network. Resolved from a post's
 * platform by `SocialPlatform::client()`. Connecting an account stays on
 * each driver itself: every network connects its own way.
 */
interface SocialClientInterface
{
    /**
     * Publishes as the account. Null when this network is not published
     * through its API, and nothing was sent. `$image` is the picked image,
     * given on the networks that need one (`SocialPlatform::requiresImage()`)
     * and ignored by the others. `$video`, when the post has one, goes out
     * instead of the image on the networks that take one
     * (`SocialPlatform::supportsVideo()`) and is ignored by the others.
     *
     * @return array{uri: string, url: string}|null
     */
    public function publish(SocialAccount $account, string $text, ?string $language = null, ?Media $image = null, ?Media $video = null): ?array;

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
