<?php

namespace App\Ai\Tools;

use App\Models\Message;
use App\Services\Outreach\ReplyOutcomes;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The reply worth having. Nothing is answered automatically: the whole promise
 * is that these mails read as one person writing to another, and an agent
 * replying to a real question would end that in one message.
 */
class MarkNeedsHuman implements Tool
{
    public function __construct(private Message $reply) {}

    public function description(): Stringable|string
    {
        return <<<'TEXT'
        Use this when a person has to read the reply and answer it themselves:
        they are interested, they ask a question, they want a price or a call, or
        the message is ambiguous enough that guessing would be worse than
        waiting.

        Set `interested` to true only when they are plainly positive: that is
        the number the user judges the whole product on, so an ambiguous
        "possibly, tell me more" is `interested: false` and still lands at the
        top of their inbox.
        TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        app(ReplyOutcomes::class)->needsHuman($this->reply, (bool) ($request['interested'] ?? false));

        return 'Flagged for the user. The sequence stays paused until they answer.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'interested' => $schema->boolean()
                ->description('True only when the reply is plainly positive.')
                ->required(),
            'summary' => $schema->string()
                ->description('One sentence on what they want, shown beside the reply.')
                ->required(),
        ];
    }
}
