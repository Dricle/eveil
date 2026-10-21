<?php

namespace App\Ai\Tools;

use App\Enums\SocialPostStatus;
use App\Models\Project;
use App\Models\SocialPost;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Picks which of a draft's images it is published with, the click on a
 * thumbnail. No approval: nothing is published or billed, and the choice
 * can be changed again at any time.
 */
class SelectSocialPostImage implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return 'Picks which of an Instagram draft\'s images it will be published with. Image ids come from ListSocialPosts, oldest first; you cannot see the images, so only pick one the user pointed at ("the second one", "the latest").';
    }

    public function handle(Request $request): Stringable|string
    {
        $post = SocialPost::query()
            ->where('project_id', $this->project->id)
            ->where('status', SocialPostStatus::Draft)
            ->find($request->integer('social_post_id'));

        if ($post === null) {
            return 'No draft with that id exists on this project. Call ListSocialPosts first.';
        }

        $image = $post->media()->where('collection_name', 'images')->find($request->integer('image_id'));

        if ($image === null) {
            return 'That draft has no image with that id. Call ListSocialPosts for its image ids.';
        }

        $post->update(['image_media_id' => $image->id]);

        return 'Done: the draft will be published with that image.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'social_post_id' => $schema->integer()->description('The Instagram draft, from ListSocialPosts.')->required(),
            'image_id' => $schema->integer()->description('One of that draft\'s image ids, from ListSocialPosts.')->required(),
        ];
    }
}
