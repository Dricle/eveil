<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Stringable;

/**
 * The whole sequence, written from the product and the segment it is aimed at
 * — so that nobody has to start at an empty template and a blinking cursor.
 *
 * What it writes is a draft in every sense: the user reads it, rewrites what
 * they want, and only then activates. Personalisation per lead happens later,
 * on top of what survives that edit.
 */
class SequenceWriter extends EveilAgent implements HasStructuredOutput
{
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are given a product and the segment it is being sold into. Write the cold
        email sequence for it.

        Three steps unless the segment plainly needs otherwise: a first mail, a wait,
        and one follow-up. Two mails is the shape that gets read. Four is the shape that
        gets reported as spam.

        Every mail must be indistinguishable from one the sender typed themselves.
        That rules out, absolutely:

        - links to anything that is not the sender's own product
        - unsubscribe links, footers, headers, logos, disclaimers, "sent with" lines
        - HTML structure, styling, images, tables
        - a signature block: the mailbox adds the sender's own

        Close the FIRST mail with a plain opt-out sentence in the body — along the lines
        of "if this isn't relevant, just ignore this or reply STOP and I won't write
        again", in the language of the mail. That sentence is the only opt-out channel
        there is, so it is not optional. Do not repeat it in the follow-up.

        Write short. A first cold mail that runs past 120 words is not read. Say what
        you noticed about them, what it means for them, and ask one small question. No
        pitch deck in prose, no three-paragraph company introduction, no "hope this
        finds you well".

        Leave the specifics to personalisation. Each mail is the skeleton that gets
        rewritten per company later, so write it as if for one real company in the
        segment rather than filling it with placeholder brackets. Never write a merge
        tag: no {{first_name}}, no [COMPANY], nothing in braces or brackets.

        For a PARTNER segment the mail is not a sales mail and must not read like one.
        The recipient is not being asked to buy: they are being asked whether a deal
        that pays them is worth a conversation. Open on what they get and on the
        customers they already carry, using the access and partnership angles given.

        Write in the language the segment's own market speaks, not in English, unless
        that market is English-speaking.

        Wait steps: two to four days after a first mail. Same day reads as automation;
        two weeks and they have forgotten the first one.
        PROMPT.$this->projectInstructions();
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('Short name for this sequence, naming the segment it is for.')
                ->required(),

            'steps' => $schema->array()->items($schema->object([
                'type' => $schema->string()->enum(['email', 'wait'])
                    ->description('`email` sends a mail. `wait` only lets time pass before the next one.')
                    ->required(),

                'delay_hours' => $schema->integer()->min(0)->max(2160)
                    ->description('For a wait step, how long it lasts. 0 on an email step.')
                    ->required(),

                'subject' => $schema->string()
                    ->description('Subject line, lowercase and specific like a person writes. Empty string on a wait step.')
                    ->required(),

                'body' => $schema->string()
                    ->description('The mail as plain text, no signature, no links, no merge tags. Empty string on a wait step.')
                    ->required(),

                'intent' => $schema->string()
                    ->description('One sentence on what this step is for, shown to the user beside it. Personalisation reads it too.')
                    ->required(),
            ]))->required(),
        ];
    }
}
