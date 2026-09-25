<?php

namespace App\Ai\Tools;

use App\Enums\SocialPlatform;
use App\Jobs\GenerateSocialPost;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Queues a new X or Bluesky post about what the user just told Evie ("we
 * just shipped X"). Evie writes the brief, not the post: `SocialPostWriter`
 * writes every one, so a post from chat follows the same tone box, examples
 * bank and length limit as one from the cadence. Same shape as
 * `DraftArticle`. Needs no approval: it only drafts, nothing is published.
 */
class DraftSocialPost implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return <<<'TEXT'
        Queues a new X or Bluesky post about what the user just told you - a
        feature that shipped, news worth sharing. Pass the network and a brief:
        what the post is about and every concrete detail the user gave. It is
        written in the background and lands in the X & Bluesky queue as a draft
        within a minute or two; it is never published on its own. Call it once per
        network when the user wants both.
        TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        $platform = SocialPlatform::tryFrom($request->string('platform')->value());

        if ($platform === null) {
            return 'platform must be x or bluesky.';
        }

        $brief = trim($request->string('brief')->value());

        if ($brief === '') {
            return 'Pass a brief: what the post is about.';
        }

        GenerateSocialPost::dispatch($this->project, $platform, $brief);

        return "The {$platform->label()} post is being written. It will appear as a draft in the X & Bluesky queue shortly.";
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'platform' => $schema->string()->enum(['x', 'bluesky'])->description('Which network the post is for.')->required(),
            'brief' => $schema->string()
                ->description('What the post is about, with every concrete detail the user gave: the feature, who it is for, any link.')
                ->required(),
        ];
    }
}
