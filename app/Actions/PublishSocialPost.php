<?php

namespace App\Actions;

use App\Enums\SocialAccountStatus;
use App\Enums\SocialPostStatus;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Services\Bluesky\BlueskyClient;
use App\Services\Bluesky\SignInRefused;
use Throwable;

/**
 * Publishes a Bluesky post, synchronously: a couple of HTTP calls, not worth
 * a queue. Same failure rule as `PublishLinkedinPost`: the row stays `Draft`
 * with `last_error` set, so the same Approve button is the retry.
 *
 * X never comes through here: it is posted by hand, see
 * `MarkSocialPostPublished`.
 */
class PublishSocialPost
{
    public function __construct(private BlueskyClient $client) {}

    public function handle(SocialPost $post, SocialAccount $account): void
    {
        $post->update(['social_account_id' => $account->id]);

        try {
            $published = $this->client->publish($account, $post->body, $post->project->default_language);
        } catch (Throwable $e) {
            $post->update(['last_error' => $e->getMessage()]);

            // A refused sign-in is the account's problem, not this post's:
            // it stops the cadence and asks for a reconnect in Settings.
            if ($e instanceof SignInRefused) {
                $account->update(['status' => SocialAccountStatus::Error, 'last_error' => $e->getMessage()]);
            }

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
