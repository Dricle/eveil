<?php

namespace App\Ai\Tools;

use App\Enums\LinkedinPostSourceType;
use App\Enums\LinkedinPostStatus;
use App\Models\LinkedinPost;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Lands a hand-composed LinkedIn post draft in the same approval queue every
 * other source uses. Deliberately does NOT publish: same reasoning as
 * `.ai/rules/tools.md`'s "there is deliberately no tool that sends a reply" -
 * publishing to a public feed is exactly the kind of one-way action that
 * should not be one chat message away from irreversible. The final publish
 * click always happens in the LinkedIn posts queue, whatever channel the
 * draft came from.
 */
class DraftLinkedinPost implements Tool
{
    public function __construct(private Project $project) {}

    public function description(): Stringable|string
    {
        return <<<'TEXT'
        Drafts a LinkedIn post from what the user just told you - a feature that
        shipped, a topic they want covered - and puts it in their LinkedIn posts
        queue for review. It does NOT post it: the user still approves and
        publishes it themselves from that screen.
        TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        LinkedinPost::create([
            'project_id' => $this->project->id,
            'source_type' => LinkedinPostSourceType::Manual,
            'evidence' => $request->string('evidence')->value(),
            'body' => $request->string('body')->value(),
            'status' => LinkedinPostStatus::Draft,
        ]);

        return 'Draft saved to the LinkedIn posts queue for review.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'body' => $schema->string()->description('The full post text, written as the user would post it.')->required(),
            'evidence' => $schema->string()->description('One line on what this is about, shown beside the draft in the queue.')->required(),
        ];
    }
}
