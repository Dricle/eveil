<?php

namespace App\Ai\Tools;

use App\Enums\SocialPlatform;
use App\Enums\SocialPostSourceType;
use App\Enums\SocialPostStatus;
use App\Models\Project;
use App\Models\SocialPost;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Lands a hand-composed X or Bluesky draft in the same queue every other
 * source uses. Never publishes, same reasoning as `DraftLinkedinPost`.
 */
class DraftSocialPost implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return <<<'TEXT'
        Drafts an X or a Bluesky post from what the user just told you and puts it
        in their X & Bluesky queue for review. It does NOT post it. Keep it under
        280 characters for X and 300 for Bluesky, product URL included. Call it
        once per network when the user wants both.
        TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        $platform = SocialPlatform::tryFrom($request->string('platform')->value());

        if ($platform === null) {
            return 'platform must be x or bluesky.';
        }

        SocialPost::create([
            'project_id' => $this->project->id,
            'platform' => $platform,
            'source_type' => SocialPostSourceType::Manual,
            'evidence' => $request->string('evidence')->value(),
            'body' => $request->string('body')->value(),
            'status' => SocialPostStatus::Draft,
        ]);

        return "Draft saved to the {$platform->label()} queue for review.";
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'platform' => $schema->string()->enum(['x', 'bluesky'])->description('Which network the post is for.')->required(),
            'body' => $schema->string()->description('The full post text, written as the user would post it.')->required(),
            'evidence' => $schema->string()->description('One line on what this is about, shown beside the draft in the queue.')->required(),
        ];
    }
}
