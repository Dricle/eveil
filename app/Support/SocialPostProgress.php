<?php

namespace App\Support;

use App\Enums\SocialPlatform;
use App\Models\Project;
use App\Models\SocialPost;
use Illuminate\Support\Facades\Cache;

/**
 * What is being written or drawn right now, so a queue page can say so while
 * the work sits in the queue or runs: a post being written for a network, an
 * image being drawn for a draft. Flags in the cache, raised when the work is
 * queued and again when it starts (a scheduled post is never queued from a
 * screen), dropped when it ends however it ends.
 *
 * The TTL is the safety net, not the mechanism: a worker killed mid-job
 * cannot drop its flag, and a spinner that never stops is worse than one
 * that stops a little early.
 */
class SocialPostProgress
{
    private const TTL_SECONDS = 900;

    public function startWriting(Project $project, SocialPlatform $platform): void
    {
        Cache::put($this->writingKey($project, $platform), true, self::TTL_SECONDS);
    }

    public function finishWriting(Project $project, SocialPlatform $platform): void
    {
        Cache::forget($this->writingKey($project, $platform));
    }

    public function isWriting(Project $project, SocialPlatform $platform): bool
    {
        return Cache::has($this->writingKey($project, $platform));
    }

    public function startDrawing(SocialPost $post): void
    {
        Cache::put($this->drawingKey($post), true, self::TTL_SECONDS);
    }

    public function finishDrawing(SocialPost $post): void
    {
        Cache::forget($this->drawingKey($post));
    }

    public function isDrawing(SocialPost $post): bool
    {
        return Cache::has($this->drawingKey($post));
    }

    private function writingKey(Project $project, SocialPlatform $platform): string
    {
        return "social-progress:writing:{$project->id}:{$platform->value}";
    }

    private function drawingKey(SocialPost $post): string
    {
        return "social-progress:drawing:{$post->id}";
    }
}
