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
 * Rewrites an existing X or Bluesky draft in place, what the queue's own
 * "Edit" button does. Drafts only, same reasoning as `UpdateLinkedinPost`.
 */
class UpdateSocialPost implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return <<<'TEXT'
        Rewrites the text of an existing X or Bluesky draft, in place - call
        ListSocialPosts first to find its id. Only works on a draft; once
        published or rejected it refuses.
        TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        $post = SocialPost::query()
            ->where('project_id', $this->project->id)
            ->find($request->integer('social_post_id'));

        if ($post === null) {
            return 'No X or Bluesky post with that id exists on this project. Call ListSocialPosts first.';
        }

        if ($post->status !== SocialPostStatus::Draft) {
            return "That post is already {$post->status->value}, so it can no longer be edited.";
        }

        $post->update(['body' => $request->string('body')->value()]);

        return "Draft #{$post->id} updated.";
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'social_post_id' => $schema->integer()->description('The draft to update, from ListSocialPosts.')->required(),
            'body' => $schema->string()->description('The full replacement post text.')->required(),
        ];
    }
}
