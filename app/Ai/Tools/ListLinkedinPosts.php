<?php

namespace App\Ai\Tools;

use App\Models\LinkedinPost;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Read-only: the LinkedIn drafts and published posts already in the queue,
 * so the model can find the one a vague request ("update the post about the
 * new pricing") refers to, rather than assuming none exists and drafting a
 * duplicate.
 */
class ListLinkedinPosts implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return 'Lists this project\'s LinkedIn posts (drafts and published), with their id, status, source and body, most recent first.';
    }

    public function handle(Request $request): Stringable|string
    {
        $posts = LinkedinPost::query()
            ->where('project_id', $this->project->id)
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (LinkedinPost $post): array => [
                'id' => $post->id,
                'status' => $post->status->value,
                'source_type' => $post->source_type->value,
                'variant' => $post->variant?->value,
                'evidence' => $post->evidence,
                'body' => $post->body,
            ]);

        if ($posts->isEmpty()) {
            return 'This project has no LinkedIn posts yet.';
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
