<?php

namespace App\Ai\Tools;

use App\Models\Message;
use App\Services\Outreach\ReplyOutcomes;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * A machine answered, so the sequence RESUMES: it was paused the moment the
 * mail was attributed, and a fortnight's holiday must not read as a reply.
 *
 * Most of these never reach the agent: the headers say so and are read first.
 * This is for the ones that arrive with no header at all, which older systems
 * and some mail rules still manage.
 */
class IgnoreReply implements Tool
{
    public function __construct(private Message $reply) {}

    public function description(): Stringable|string
    {
        return <<<'TEXT'
        Use this ONLY for a message no person wrote to you: an out-of-office or
        holiday auto-reply, a ticket acknowledgement, a delivery notification, a
        newsletter that landed in the thread.

        The sequence resumes when you call this, so never use it for a short or
        blunt human reply: a person writing "no." is not this tool.
        TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        app(ReplyOutcomes::class)->ignore($this->reply);

        return 'Ignored as automatic. The sequence has resumed.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'kind' => $schema->string()
                ->description('What kind of automatic message this is, in three or four words.')
                ->required(),
        ];
    }
}
