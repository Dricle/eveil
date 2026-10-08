<?php

namespace App\Ai\Tools;

use App\Models\Project;
use App\Models\RedditReply;
use App\Services\Reddit\SeoThreadFinder;
use App\Services\Reddit\TopComments;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * One Reddit reply draft with the thread it answers, what the user is looking
 * at when they ask Evie to rework it. The thread itself is not stored on the
 * reply, so its post and top comments are read live from Arctic Shift, the
 * same mirror the scan used; null when the mirror is down or the post gone.
 */
class GetRedditReply implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return 'Reads one Reddit reply draft in full: the Reddit post it answers (title, text, top comments), why it was worth answering, its angle, status and body.';
    }

    public function handle(Request $request): Stringable|string
    {
        $reply = RedditReply::query()
            ->where('project_id', $this->project->id)
            ->find($request->integer('reddit_reply_id'));

        if ($reply === null) {
            return 'No Reddit reply with that id exists on this project.';
        }

        return (string) json_encode([
            'id' => $reply->id,
            'status' => $reply->status->value,
            'angle' => $reply->angle->value,
            'subreddit' => $reply->subreddit,
            'thread_title' => $reply->thread_title,
            'thread_permalink' => $reply->thread_permalink,
            'thread' => $this->thread($reply->thread_permalink),
            'evidence' => $reply->evidence,
            'body' => $reply->body,
        ]);
    }

    /**
     * @return array{title: string, text: string, top_comments: array<int, string>}|null
     */
    private function thread(string $permalink): ?array
    {
        $post = app(SeoThreadFinder::class)->fetchPost($permalink);

        if ($post === null) {
            return null;
        }

        return [
            'title' => (string) ($post['title'] ?? ''),
            'text' => (string) ($post['selftext'] ?? ''),
            'top_comments' => app(TopComments::class)->for((string) ($post['id'] ?? ''))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'reddit_reply_id' => $schema->integer()->description('The Reddit reply to read.')->required(),
        ];
    }
}
