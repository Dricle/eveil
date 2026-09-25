<?php

namespace App\Actions;

use App\Enums\SocialPlatform;
use App\Enums\SocialPostStatus;
use App\Models\SocialPost;
use App\Services\Bluesky\BlueskyClient;
use App\Support\Settings;

/**
 * Reads like counts on recent Bluesky posts and, past a threshold, marks one
 * as a proven example for its own project's writer. Free and public on
 * Bluesky, so every recent post is read. X is never read: its API is paid.
 *
 * Unlike LinkedIn there is no shared instance-wide pool to feed: a proven
 * post only ever teaches its own project.
 *
 * Runs across every project on the instance, same as
 * `FetchLinkedinPostStats`: no `CurrentProject` is set from a console
 * command, so the `BelongsToProject` scope does not apply.
 */
class FetchSocialPostStats
{
    public function __construct(private BlueskyClient $client, private Settings $settings) {}

    /**
     * How many posts were newly promoted.
     */
    public function handle(): int
    {
        $minLikes = $this->settings->int('social_examples.min_likes');

        // A post's engagement settles within days, so nothing older than 30
        // is worth reading again.
        $posts = SocialPost::query()
            ->where('platform', SocialPlatform::Bluesky)
            ->where('status', SocialPostStatus::Published)
            ->whereNotNull('external_id')
            ->where('published_at', '>=', now()->subDays(30))
            ->get();

        if ($posts->isEmpty()) {
            return 0;
        }

        $likes = $this->client->likeCounts($posts->pluck('external_id')->all());
        $promoted = 0;

        foreach ($posts as $post) {
            if (! isset($likes[$post->external_id])) {
                continue;
            }

            $count = $likes[$post->external_id];
            $promote = $post->promoted_at === null && $count >= $minLikes;

            $post->update([
                'likes_count' => $count,
                'stats_checked_at' => now(),
                ...($promote ? ['promoted_at' => now()] : []),
            ]);

            $promoted += (int) $promote;
        }

        return $promoted;
    }
}
