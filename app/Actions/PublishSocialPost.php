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
 * HTTP calls, not worth a queue. A failure leaves the row `Draft` with
 * `last_error` set, never a status of its own, so the same Approve button is
 * the retry. A driver that does not publish (X, posted by hand, see
 * `MarkSocialPostPublished`) leaves the draft untouched.
 *
 * A network that needs a picture and has none yet (its drawing failed when
 * the draft was written) gets one drawn here first, from the post itself:
 * approving again is the retry for that too. A post with a video sends it
 * as well, and the network uses it instead of the image.
 */
class PublishSocialPost
{
    public function __construct(private AddSocialPostImage $addImage) {}

    public function handle(SocialPost $post, SocialAccount $account): void
    {
        $post->update(['social_account_id' => $account->id]);

        try {
            $video = $post->video;

            // A video goes out instead of the image: no image to draw then.
            if ($post->platform->requiresImage() && $post->image_media_id === null && $video === null) {
                $this->addImage->handle($post);
            }

            $published = $post->platform->client()->publish($account, $post->body, $post->project->default_language, $post->image, $video);
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
