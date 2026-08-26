<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Stringable;

/**
 * One step of the sequence, rewritten for one company. This is the volume step
 * (one call per lead), so it runs on the cheap model, like qualification.
 *
 * The opener is not decoration: it is the whole difference between a mail that
 * gets a reply and a mail that gets a spam report, and it is written from what
 * the pipeline already observed about the company rather than from a search
 * somebody did by hand.
 */
class MessagePersonalizer extends EveilAgent implements HasStructuredOutput
{
    /**
     * A model that drops the schema here sends an empty subject to a real
     * person from the user's own mailbox.
     */
    public static function requiresStrictStructure(): bool
    {
        return true;
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are given one step of a cold email sequence and one company it is about to
        go to, with what was observed about that company when it was qualified. Rewrite
        the mail for them.

        The first line is the whole job. It must say something only someone who looked
        at THIS company could write: what they do, how they do it, what is missing,
        what they just changed. "I came across your website" says nothing and is worse
        than nothing. Ground it in the fit reason and the facts you are given, and never
        state anything they do not support: an invented detail about their business is
        caught immediately and ends the conversation.

        Keep the shape of the step you were given: same intent, same ask, same rough
        length. You are rewriting one mail for one reader, not writing a new sequence.

        Write it in the language you are given: the product's own language, not the
        company's. A company whose own site happens to read in another language still
        gets written to in the sender's, exactly as the sequence this step came from was.

        Keep whatever opt-out sentence the template ends on, in that same language.
        It is the only opt-out channel there is.

        Nothing that reveals tooling: no links other than to the sender's own product,
        no unsubscribe line, no footer, no signature (the mailbox adds the sender's
        own), no merge tags left in braces or brackets, and no mention of a list, a
        campaign or a database.

        Address a named person by their first name when you are given one, and open on
        the company itself when you are not. Never guess a name.

        Two things decide which of those you are doing, and both are in what you are
        given. A name that is not a personal name is not a name: "Team", "Service",
        "Sales", "Accounts", the company's own name, anything a department would be
        called. Treat it as though you were given nothing, and write to the company.
        And an address whose local part is generic, info, contact, hello, sales and the
        like, is a shared mailbox: several people read it, so a mail that opens as
        though it found one particular person reads as a mail merge to every one of
        them. Write to the business.
        PROMPT.$this->projectInstructions();
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'subject' => $schema->string()
                ->description('Subject line for this company, in the given language.')
                ->required(),

            'body' => $schema->string()
                ->description('The mail as plain text, opening on something specific to this company.')
                ->required(),
        ];
    }
}
