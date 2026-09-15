<?php

namespace App\Jobs;

use App\Enums\LinkedinPostStatus;
use App\Models\LinkedinPost;
use App\Services\Linkedin\LinkedinClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Low volume, never bursty: this stays on the `default` queue rather than
 * earning its own Horizon supervisor, per `.ai/rules/jobs.md`'s "one queue
 * per rhythm" reasoning.
 */
class PublishLinkedinPost implements ShouldQueue
{
    use Queueable;

    public function __construct(public LinkedinPost $post) {}

    public function handle(LinkedinClient $client): void
    {
        $account = $this->post->linkedinAccount;

        if ($account === null) {
            $this->post->update(['status' => LinkedinPostStatus::Failed, 'last_error' => 'No LinkedIn account attached.']);

            return;
        }

        try {
            $urn = $client->publishPost($account, $this->post->body);
        } catch (Throwable $e) {
            $this->post->update(['status' => LinkedinPostStatus::Failed, 'last_error' => $e->getMessage()]);

            return;
        }

        $this->post->update([
            'status' => LinkedinPostStatus::Published,
            'urn' => $urn,
            'published_at' => now(),
        ]);
    }
}
