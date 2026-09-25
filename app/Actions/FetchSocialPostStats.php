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
 * a threshold, copies one into that network's shared instance-wide bank and
 * marks it proven for its own project. The only automatic way into a bank,
 * same trust rule as `FetchLinkedinPostStats`. Bluesky's counts are free and
 * public; X's driver reads nothing, since its API is paid.
 *
 * Runs across every project on the instance, same as
 * `FetchLinkedinPostStats`: no `CurrentProject` is set from a console
 * command, so the `BelongsToProject` scope does not apply.
 */
class FetchSocialPostStats
{
    public function __construct(private Settings $settings) {}

    /**
     * How many posts newly joined the shared bank.
     */
    public function handle(): int
    {
        $minLikes = $this->settings->int('social_examples.min_likes');

        // A post's engagement settles within days, so nothing older than 30
        // is worth reading again.
        $posts = SocialPost::query()
            ->where('status', SocialPostStatus::Published)
            ->whereNotNull('external_id')
            ->where('published_at', '>=', now()->subDays(30))
            ->get();

        if ($posts->isEmpty()) {
            return 0;
        }

        // Per network, so two networks' ids can never collide.
        $likes = [];

        foreach ($posts->groupBy(fn (SocialPost $post): string => $post->platform->value) as $platform => $group) {
            $likes[$platform] = SocialPlatform::from($platform)->client()->likeCounts($group->pluck('external_id')->all());
        }

        $promoted = 0;

        foreach ($posts as $post) {
            if (! isset($likes[$post->platform->value][$post->external_id])) {
                continue;
            }

            $count = $likes[$post->platform->value][$post->external_id];

            $post->update(['likes_count' => $count, 'stats_checked_at' => now()]);

            if ($count < $minLikes) {
                continue;
            }

            // Into that network's shared bank, once. A post the user already
            // marked successful by hand still earns its place here: the
            // click never wrote to the bank, the measured number does.
            $example = SocialPostExample::query()->firstOrCreate(
                ['social_post_id' => $post->id],
                ['platform' => $post->platform, 'body' => $post->body, 'source' => SocialPostExampleSource::Promoted],
            );

            if ($post->promoted_at === null) {
                $post->update(['promoted_at' => now()]);
            }

            $promoted += (int) $example->wasRecentlyCreated;
        }

        return $promoted;
    }
}
