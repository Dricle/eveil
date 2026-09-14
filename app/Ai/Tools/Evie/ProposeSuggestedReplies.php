<?php

namespace App\Ai\Tools\Evie;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * No side effect: its only purpose is to appear as a tool call in the stream
 * so the chat panel can render its arguments as clickable suggestion
 * buttons, since streaming rules out returning them as structured output
 * alongside the prose (`StreamsText` throws on a `HasStructuredOutput`
 * agent).
 */
class ProposeSuggestedReplies implements Tool
{
    public function description(): Stringable|string
    {
        return <<<'TEXT'
        Call this at the end of a turn to offer the user 2-4 short, ready-to-send
        replies instead of making them type one, when the obvious next messages
        are predictable (e.g. after asking "should I start this discovery run?",
        offer "Yes, start it" / "No, let me adjust the profile first").

        Optional. Skip it when there is nothing obvious to suggest, or when the
        turn already ends with a pending approval card.
        TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        return 'ok';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'replies' => $schema->array()->items($schema->string())
                ->description('2-4 short, ready-to-send replies the user could pick instead of typing.')
                ->required(),
        ];
    }
}
