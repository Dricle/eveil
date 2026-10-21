<?php

namespace App\Jobs;

use App\Actions\AddSocialPostImage;
use App\Models\SocialPost;
use App\Support\CurrentProject;
use App\Support\SocialPostProgress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * One more image for a draft, asked for from its queue. Queued rather than
 * drawn during the click: a drawing takes longer than a request should, and
 * the queue page already rereads on its own, so the new image simply shows
 * up beside the others.
 *
 * No retry: a second attempt would bill a second image for one click. A
 * failure lands on the draft, where the user sees it and can ask again.
 */
class GenerateSocialPostImage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public SocialPost $post, public ?string $note = null)
    {
        $this->onQueue('ai');
    }

    public function handle(AddSocialPostImage $addImage, CurrentProject $currentProject, SocialPostProgress $progress): void
    {
        $progress->startDrawing($this->post);

        try {
            $this->draw($addImage, $currentProject);
        } finally {
            $progress->finishDrawing($this->post);
        }
    }

    private function draw(AddSocialPostImage $addImage, CurrentProject $currentProject): void
    {
        $currentProject->run($this->post->project, function () use ($addImage): void {
            try {
                $addImage->handle($this->post, $this->note);
            } catch (Throwable $e) {
                $this->post->update(['last_error' => "The new image could not be generated: {$e->getMessage()}"]);

                return;
            }

            $this->post->update(['last_error' => null]);
        });
    }
}
