<?php

namespace App\Ai\Tools;

use App\Enums\LinkedinPostStatus;
use App\Models\LinkedinPost;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Corrects an existing LinkedIn draft's text - what the queue's own "Edit"
 * button does, on the user's behalf - rather than DraftLinkedinPost creating
 * a second, duplicate draft. Needs no approval, same reasoning as
 * UpdateKnowledgeBase: nothing is spawned and nothing is published, only
 * edited.
 *
 * Only ever touches a DRAFT: once approved or published, the queue's own
 * "Edit" button is gone too, and rewriting a live or published post from
 * chat would silently change what was actually reviewed or already went out.
 */
class UpdateLinkedinPost implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return <<<'TEXT'
        Rewrites the text of an existing LinkedIn draft, in place - call
        ListLinkedinPosts first to find its id. Only works on a post still
        awaiting approval; once approved, rejected or published it refuses,
        since the queue's own edit option is gone by then too.
        TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        $post = LinkedinPost::query()
            ->where('project_id', $this->project->id)
            ->find($request->integer('linkedin_post_id'));

        if ($post === null) {
            return 'No LinkedIn post with that id exists on this project. Call ListLinkedinPosts first.';
        }

        if ($post->status !== LinkedinPostStatus::Draft) {
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
            'linkedin_post_id' => $schema->integer()
                ->description('The draft to update, from ListLinkedinPosts.')
                ->required(),
            'body' => $schema->string()->description('The full replacement post text.')->required(),
        ];
    }
}
