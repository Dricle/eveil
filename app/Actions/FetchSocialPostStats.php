<?php

namespace App\Actions;

use App\Enums\SocialPlatform;
use App\Enums\SocialPostExampleSource;
use App\Enums\SocialPostStatus;
use App\Models\SocialPost;
use App\Models\SocialPostExample;
use App\Support\Settings;

/**
 * Reads like counts on recent posts through each network's driver and, past
 * that network's threshold, copies one into its shared instance-wide bank
 * and marks it proven for its own project. The only automatic way into a
 * bank, deliberately: a real, externally-measured number is trusted the way
 * a user's self-reported "mark as successful" click is not.
 *
 * Bluesky's counts are free and public. LinkedIn's need the account's
 * separate performance-polling connection, and its driver skips a post
 * without one. X is skipped outright: its API is paid, so it has no
 * threshold (`SocialPlatform::minLikesSetting()`).
 *
 * Runs across every project on the instance: no `CurrentProject` is set from
 * a console command, so the `BelongsToProject` scope does not apply.
 */
class FetchSocialPostStats
{
    public function __construct(private Settings $settings) {}

    /**
     * How many posts newly joined a shared bank.
     */
    public function handle(): int
    {
        // A post's engagement settles within days, so nothing older than 30
        // is worth reading again.
        $posts = SocialPost::query()
            ->where('status', SocialPostStatus::Published)
            ->whereNotNull('external_id')
            ->where('published_at', '>=', now()->subDays(30))
            ->get();

        $promoted = 0;

        foreach ($posts->groupBy(fn (SocialPost $post): string => $post->platform->value) as $platform => $group) {
            $platform = SocialPlatform::from($platform);
            $setting = $platform->minLikesSetting();

            if ($setting === null) {
                continue;
            }

            $minLikes = $this->settings->int($setting);
            $likes = $platform->client()->likeCounts($group);

            foreach ($group as $post) {
                if (! isset($likes[$post->id])) {
                    continue;
                }

                $post->update(['likes_count' => $likes[$post->id], 'stats_checked_at' => now()]);

                if ($likes[$post->id] < $minLikes) {
                    continue;
                }

                // Into that network's shared bank, once. A post the user
                // already marked successful by hand still earns its place:
                // the click never wrote to the bank, the measured number does.
                $example = SocialPostExample::query()->firstOrCreate(
                    ['social_post_id' => $post->id],
                    ['platform' => $post->platform, 'body' => $post->body, 'source' => SocialPostExampleSource::Promoted],
                );

                if ($post->promoted_at === null) {
                    $post->update(['promoted_at' => now()]);
                }

                $promoted += (int) $example->wasRecentlyCreated;
            }
        }

        return $promoted;
    }
}
