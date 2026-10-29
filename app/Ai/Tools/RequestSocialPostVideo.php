<?php

namespace App\Ai\Tools;

use App\Enums\SocialPostStatus;
use App\Jobs\GenerateSocialPostVideo;
use App\Models\Project;
use App\Models\SocialPost;
use App\Support\SocialPostProgress;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Makes the short video for a LinkedIn, Bluesky or Instagram draft, or a
 * new version of it from what the user wants changed, the editor's "Make
 * the video" and feedback box. Gated behind approval: every version is
 * written and rendered, and billed, unlike a draft or an edit.
 */
class RequestSocialPostVideo implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(private Project $project)
    {
        $this->requireApproval('This makes a new version of the video, which costs credits.');
    }

    public function description(): Stringable|string
    {
        return <<<'TEXT'
        Makes the short vertical video that goes out with a LinkedIn, Bluesky
        or Instagram draft (as a Reel there, instead of the image), or a new
        version of it. Pass what should change,
        in the user's words, and the second of the video they mean when they
        said one ("at 0:04 it zooms into nothing" is 4). Without feedback it
        makes the first version, or a fresh take. Takes a minute or two; the
        draft shows it once made, and its editor opens from there.
        TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        $post = SocialPost::query()
            ->where('project_id', $this->project->id)
            ->where('status', SocialPostStatus::Draft)
            ->find($request->integer('social_post_id'));

        if ($post === null || ! $post->platform->supportsVideo()) {
            return 'No LinkedIn, Bluesky or Instagram draft with that id exists on this project. Call ListSocialPosts first.';
        }

        $progress = app(SocialPostProgress::class);

        if ($progress->isDirecting($post)) {
            return 'A version of that video is already being made. Wait for it before asking for another.';
        }

        $progress->startDirecting($post);

        GenerateSocialPostVideo::dispatch($post, trim($request->string('feedback')->value()) ?: null, $request->filled('at') ? $request->float('at') : null);

        return 'The video is being made. It will show on the draft in a minute or two.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'social_post_id' => $schema->integer()->description('The LinkedIn, Bluesky or Instagram draft, from ListSocialPosts.')->required(),
            'feedback' => $schema->string()->description('What should change from the current version, in the user\'s words. Leave empty for the first version or a fresh take.'),
            'at' => $schema->number()->description('The second of the video the feedback is about, when the user said one.'),
        ];
    }
}
