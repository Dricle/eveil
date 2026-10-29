<?php

namespace App\Ai\Tools;

use App\Models\Project;
use App\Models\SocialPost;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * One post by id, what the user is looking at when they ask Evie to rework
 * it. ListSocialPosts only shows the ten most recent, so an older draft
 * handed over from its queue would otherwise be out of reach.
 */
class GetSocialPost implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return 'Reads one LinkedIn, X, Bluesky or Instagram post in full by its id: network, status, source and body.';
    }

    public function handle(Request $request): Stringable|string
    {
        $post = SocialPost::query()
            ->where('project_id', $this->project->id)
            ->find($request->integer('social_post_id'));

        if ($post === null) {
            return 'No post with that id exists on this project.';
        }

        return (string) json_encode([
            'id' => $post->id,
            'platform' => $post->platform->value,
            'status' => $post->status->value,
            'source_type' => $post->source_type->value,
            'variant' => $post->variant?->value,
            'evidence' => $post->evidence,
            'body' => $post->body,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'social_post_id' => $schema->integer()->description('The post to read.')->required(),
        ];
    }
}
