<?php

namespace App\Jobs;

use App\Actions\DirectSocialPostVideo;
use App\Actions\PublishSocialPostIfAutonomous;
use App\Enums\SocialPostStatus;
use App\Models\SocialPost;
use App\Notifications\SocialPostDrafted;
use App\Support\CurrentProject;
use App\Support\SocialPostProgress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Makes the video for a draft, or a new version of it when `$feedback` says
 * what to change. Queued: writing the script and filming it frame by frame
 * take a minute or two, and the queue page and the editor reread on their
 * own, so the video simply shows up.
 *
 * `$publishAfter` is set when the drafts were just written on an autonomous
 * network: the post waited for its video, and goes out now, with it or, if
 * the video failed, with its image alone. Whatever is still a draft then
 * gets the email the writer held back.
 *
 * No retry: a second attempt would bill a second script for one click. A
 * failure lands on the draft, where the user sees it and can ask again.
 */
class GenerateSocialPostVideo implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(
        public SocialPost $post,
        public ?string $feedback = null,
        public ?float $at = null,
        public bool $publishAfter = false,
    ) {
        $this->onQueue('ai');
    }

    public function handle(DirectSocialPostVideo $direct, PublishSocialPostIfAutonomous $publish, CurrentProject $currentProject, SocialPostProgress $progress): void
    {
        $progress->startDirecting($this->post);

        try {
            $currentProject->run($this->post->project, function () use ($direct): void {
                try {
                    $direct->handle($this->post, $this->feedback, $this->at);
                } catch (Throwable $e) {
                    $this->post->update(['last_error' => "The video could not be made: {$e->getMessage()}"]);
                }
            });
        } finally {
            $progress->finishDirecting($this->post);
        }

        if (! $this->publishAfter) {
            return;
        }

        $currentProject->run($this->post->project, function () use ($publish): void {
            $publish->handle($this->post->project, $this->post->platform, collect([$this->post]));

            if ($this->post->fresh()?->status === SocialPostStatus::Draft) {
                Notification::send($this->post->project->notifiableUsers(), SocialPostDrafted::for($this->post->project, $this->post->platform));
            }
        });
    }
}
