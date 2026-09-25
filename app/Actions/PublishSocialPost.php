<?php

namespace App\Actions;

use App\Enums\SocialAccountStatus;
use App\Enums\SocialPostStatus;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Services\Bluesky\SignInRefused;
use Throwable;

/**
 * Publishes a post through its network's driver, synchronously: a couple of
 * HTTP calls, not worth a queue. Same failure rule as `PublishLinkedinPost`:
 * the row stays `Draft` with `last_error` set, so the same Approve button is
 * the retry. A driver that does not publish (X, posted by hand, see
 * `MarkSocialPostPublished`) leaves the draft untouched.
 */
class PublishSocialPost
{
    public function handle(SocialPost $post, SocialAccount $account): void
    {
        $post->update(['social_account_id' => $account->id]);

        try {
            $published = $post->platform->client()->publish($account, $post->body, $post->project->default_language);
        } catch (Throwable $e) {
            $post->update(['last_error' => $e->getMessage()]);

            // A refused sign-in is the account's problem, not this post's:
            // it stops the cadence and asks for a reconnect in Settings.
            if ($e instanceof SignInRefused) {
                $account->update(['status' => SocialAccountStatus::Error, 'last_error' => $e->getMessage()]);
            }

            return;
        }

        if ($published === null) {
            return;
        }

        $post->update([
            'status' => SocialPostStatus::Published,
            'external_id' => $published['uri'],
            'url' => $published['url'],
            'published_at' => now(),
            'last_error' => null,
        ]);
    }
}
