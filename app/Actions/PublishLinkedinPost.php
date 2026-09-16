<?php

namespace App\Actions;

use App\Enums\LinkedinPostStatus;
use App\Models\LinkedinAccount;
use App\Models\LinkedinPost;
use App\Services\Linkedin\LinkedinClient;
use Throwable;

/**
 * One HTTP call to LinkedIn - not worth a queue. Called synchronously from
 * `LinkedinPostController::approve()`: approving and publishing are the same
 * action, so there is no separate `approved` status to sit in while a job
 * waits its turn.
 *
 * On failure the row stays `Draft` with `last_error` populated, never its
 * own `Failed` status - that would overwrite the fact this was a draft
 * awaiting review the moment an API call fails. The same Approve button
 * already showing for a draft is what makes this retryable, with no
 * separate code path.
 */
class PublishLinkedinPost
{
    public function __construct(private LinkedinClient $client) {}

    public function handle(LinkedinPost $post, LinkedinAccount $account): void
    {
        try {
            $urn = $this->client->publishPost($account, $post->body);
        } catch (Throwable $e) {
            $post->update(['last_error' => $e->getMessage()]);

            return;
        }

        $post->update([
            'status' => LinkedinPostStatus::Published,
            'urn' => $urn,
            'published_at' => now(),
            'last_error' => null,
        ]);
    }
}
