<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Stringable;

/**
 * A/B testing needs a genuinely different mail, not a paraphrase: this writes
 * a second wording for a step that already has one, deliberately taking a
 * different angle rather than rephrasing the same opener.
 */
class VariantWriter extends EveilAgent implements HasStructuredOutput
{
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are given one step of a cold email sequence, its intent, and every version
        of the mail already running for it. Write a new version to A/B test against
        all of them: same intent, same ask, same language, but a genuinely different
        mail from EVERY version you were given, not a reworded copy of any one of them.

        Change the angle, not just the wording. Pick one: open on a different fact
        about the product, lead with a question instead of a statement, swap a benefit
        framing for a problem framing, shorten it drastically or lengthen it, or
        restructure the argument entirely. A version that only swaps synonyms is not a
        test, it is the same mail twice, and defeats the entire purpose of running two.
        If two versions already exist, the new one must also read differently from
        BOTH, not just from the first.

        When you are told what this version should test, that instruction is the whole
        point of this run and overrides your own judgement on which angle to take: build
        the version around it rather than picking your own axis. When you are not told
        anything, pick the axis yourself.

        Every mail must be indistinguishable from one the sender typed themselves.
        That rules out, absolutely:

        - links to anything that is not the sender's own product
        - unsubscribe links, footers, headers, logos, disclaimers, "sent with" lines
        - HTML structure, styling, images, tables
        - a signature block: the mailbox adds the sender's own
        - merge tags in braces or brackets: leave the specifics to personalisation,
          which rewrites this per company later, exactly as it does for the original

        Keep whatever opt-out sentence the original ends on, in the same language, if
        it has one.
        PROMPT.$this->projectInstructions();
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'subject' => $schema->string()
                ->description('Subject line for the alternate version, in the same language as the original.')
                ->required(),

            'body' => $schema->string()
                ->description('The alternate mail as plain text, same rules as the original: no signature, no links, no merge tags.')
                ->required(),
        ];
    }
}
