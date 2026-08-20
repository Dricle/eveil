<?php

namespace App\Ai\Tools;

use App\Models\Message;
use App\Services\Outreach\ReplyOutcomes;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Wrong person, right company. The sequence stops for them and the user is shown
 * who they named: writing to a third party on a stranger's word is how a
 * mailbox earns a complaint, so nothing is sent automatically.
 */
class AskForRightContact implements Tool
{
    public function __construct(private Message $reply) {}

    public function description(): Stringable|string
    {
        return <<<'TEXT'
        Use this when the person says they are not the one who decides: "that is
        not my department", "speak to our operations manager", "I have forwarded
        this to a colleague".

        Put whoever they named in `named_contact`, exactly as they wrote it, or
        leave it empty when they named nobody.
        TEXT;
    }

    public function handle(Request $request): Stringable|string
    {
        app(ReplyOutcomes::class)->wrongPerson($this->reply);

        return 'Recorded as the wrong contact. The sequence has stopped for this person and the user will see who they named.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'named_contact' => $schema->string()
                ->description('The name, role or address they pointed to. Empty string when they named nobody.')
                ->required(),
        ];
    }
}
