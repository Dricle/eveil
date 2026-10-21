<?php

namespace App\Ai\Tools;

use App\Enums\SocialPostStatus;
use App\Jobs\GenerateSocialPostImage;
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
 * Draws one more image for a draft that needs one (Instagram), the "New
 * image" button. Gated behind approval: every image is billed, unlike a
 * draft or an edit. The image already picked stays picked; choosing the new
 * one is `SelectSocialPostImage`.
 */
class RequestSocialPostImage implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(private Project $project)
    {
        $this->requireApproval('This draws a new image, which costs credits.');
    }

    public function description(): Stringable|string
    {
        return <<<'TEXT'
        Draws one more image for an Instagram draft, kept beside the ones it
        already has: nothing is replaced, and the image already picked stays
        picked. Pass what should change from the earlier images, if the user
        said ("brighter", "a close-up"). It appears on the draft within a minute
        or two; choose it afterwards with SelectSocialPostImage if the user wants it.
        TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        $post = SocialPost::query()
            ->where('project_id', $this->project->id)
            ->where('status', SocialPostStatus::Draft)
            ->find($request->integer('social_post_id'));

        if ($post === null || ! $post->platform->requiresImage()) {
            return 'No Instagram draft with that id exists on this project. Call ListSocialPosts first.';
        }

        app(SocialPostProgress::class)->startDrawing($post);

        GenerateSocialPostImage::dispatch($post, trim($request->string('note')->value()) ?: null);

        return 'A new image is being drawn for that draft. It will appear beside the others shortly.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'social_post_id' => $schema->integer()->description('The Instagram draft, from ListSocialPosts.')->required(),
            'note' => $schema->string()->description('What should change from the earlier images, in the user\'s words. Leave empty for another take on the same idea.'),
        ];
    }
}
