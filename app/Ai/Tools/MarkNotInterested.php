<?php

namespace App\Ai\Tools;

use App\Models\Message;
use App\Services\Outreach\ReplyOutcomes;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * A no that is only a no. The sequence ends and nobody is asked to do anything
 * about it — the difference from an opt-out is that they refused an offer rather
 * than refusing contact.
 */
class MarkNotInterested implements Tool
{
    public function __construct(private Message $reply) {}

    public function description(): Stringable|string
    {
        return <<<'TEXT'
        Use this when the person declines but does not ask you to stop writing:
        "we already have a supplier", "not for us", "no thanks".

        Do not use it when they ask to be removed from a list — that is
        suppress_lead. Do not use it when they are asking a question or showing
        any interest at all — that is mark_needs_human.
        TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        app(ReplyOutcomes::class)->notInterested($this->reply);

        return 'Recorded as not interested. The sequence has stopped for this person.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()
                ->description('One sentence on what they declined, for the user to read in the inbox.')
                ->required(),
        ];
    }
}
