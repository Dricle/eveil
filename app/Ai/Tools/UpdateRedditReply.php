<?php

namespace App\Ai\Tools;

use App\Enums\RedditReplyStatus;
use App\Models\Project;
use App\Models\RedditReply;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Rewrites a Reddit reply draft in place, on the user's feedback. Draft only,
 * same reasoning as `UpdateSocialPost`: a posted reply is already live on
 * Reddit, and changing it here would no longer match what is there.
 */
class UpdateRedditReply implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return <<<'TEXT'
        Rewrites the body of an existing Reddit reply draft, in place - call
        GetRedditReply first to read it. Only works on a draft; once posted or
        rejected it refuses.
        TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        $reply = RedditReply::query()
            ->where('project_id', $this->project->id)
            ->find($request->integer('reddit_reply_id'));

        if ($reply === null) {
            return 'No Reddit reply with that id exists on this project.';
        }

        if ($reply->status !== RedditReplyStatus::Draft) {
            return "That reply is already {$reply->status->value}, so it can no longer be edited.";
        }

        $reply->update(['body' => $request->string('body')->value()]);

        return "Reddit reply #{$reply->id} updated.";
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'reddit_reply_id' => $schema->integer()->description('The draft to update, from GetRedditReply.')->required(),
            'body' => $schema->string()->description('The full replacement reply text.')->required(),
        ];
    }
}
