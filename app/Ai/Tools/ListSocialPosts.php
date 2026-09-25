<?php

namespace App\Ai\Tools;

use App\Models\Project;
use App\Models\SocialPost;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Read-only: the X and Bluesky posts already in the queue, so a vague
 * request ("shorten the Bluesky post") finds the existing one rather than
 * drafting a duplicate.
 */
class ListSocialPosts implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return 'Lists this project\'s X and Bluesky posts (drafts and published), with their id, network, status, source and body, most recent first.';
    }

    public function handle(Request $request): Stringable|string
    {
        $posts = SocialPost::query()
            ->where('project_id', $this->project->id)
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (SocialPost $post): array => [
                'id' => $post->id,
                'platform' => $post->platform->value,
                'status' => $post->status->value,
                'source_type' => $post->source_type->value,
                'evidence' => $post->evidence,
                'body' => $post->body,
            ]);

        if ($posts->isEmpty()) {
            return 'This project has no X or Bluesky posts yet.';
        }

        return (string) json_encode($posts->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
