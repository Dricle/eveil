<?php

namespace App\Actions;

use App\Enums\AutonomyLevel;
use App\Enums\SocialAccountStatus;
use App\Enums\SocialPlatform;
use App\Enums\SocialPostStatus;
use App\Enums\SocialPostVariant;
use App\Models\Project;
use App\Models\SocialPost;
use Illuminate\Support\Collection;

/**
 * The network's autonomous setting: publish a fresh draft straight away. Two
 * cases still stop for a person. Several accounts on the project: which one
 * to post as is a choice nobody has made. A client win: only the anonymized
 * variant ever goes out on its own, since naming a client in public is
 * theirs to agree to, and the named sibling is rejected the same way
 * approving by hand rejects it. X is always supervised: it is posted by hand.
 *
 * Called when the drafts are written, or, when a video was asked for, once
 * the video is made (`GenerateSocialPostVideo`), so the post goes out with it.
 */
class PublishSocialPostIfAutonomous
{
    public function __construct(private PublishSocialPost $publish) {}

    /**
     * @param  Collection<int, SocialPost>  $posts
     */
    public function handle(Project $project, SocialPlatform $platform, Collection $posts): void
    {
        if ($platform->autonomyLevel($project) !== AutonomyLevel::Autonomous) {
            return;
        }

        $accounts = $project->socialAccounts()
            ->where('platform', $platform)
            ->where('status', SocialAccountStatus::Active)
            ->get();

        // Read again: the drafts were on the page while their image was drawn,
        // and the user may have rejected or edited one in the meantime.
        $post = $posts->first(fn (SocialPost $post): bool => $post->variant !== SocialPostVariant::Named)?->fresh();

        // No image and no video, no Instagram post: it stays a draft showing why.
        if ($accounts->count() !== 1 || $post === null || $post->status !== SocialPostStatus::Draft || ($platform->requiresImage() && $post->image_media_id === null && $post->video_media_id === null)) {
            return;
        }

        $post->sibling()->update([
            'status' => SocialPostStatus::Rejected,
            'rejection_reason' => 'Superseded by the other variant.',
        ]);

        $this->publish->handle($post, $accounts->sole());
    }
}
