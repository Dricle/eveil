<?php

namespace App\Ai\Tools;

use App\Models\Message;
use App\Services\Outreach\ReplyOutcomes;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The opt-out. This is the compliance path: mails carry no unsubscribe link, so
 * a reply is the only way anybody can ask us to stop, and missing one costs a
 * complaint rather than a lead.
 */
class SuppressLead implements Tool
{
    public function __construct(private Message $reply) {}

    public function description(): Stringable|string
    {
        return <<<'TEXT'
        Use this when the person asks, in any words and any language, not to be
        contacted again: "stop", "unsubscribe", "désinscrivez-moi", "remove me
        from your list", "geen mail meer", or a sentence that plainly means it.

        Prefer this tool whenever you are unsure between it and a plain refusal:
        suppressing somebody who only meant "no thanks" costs one lead, while
        writing again to somebody who asked you to stop is a complaint against
        the sender's own domain.
        TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        app(ReplyOutcomes::class)->suppress($this->reply, (string) ($request['reason'] ?? 'asked not to be contacted'));

        return 'Suppressed. This address will never be written to again for this project.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'reason' => $schema->string()
                ->description('The sentence in their reply that asks not to be contacted, quoted.')
                ->required(),
        ];
    }
}
