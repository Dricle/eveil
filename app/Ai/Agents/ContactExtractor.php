<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Stringable;

/**
 * Pulls people and addresses out of a company's own pages.
 *
 * Runs on the cheap model: this is reading, not judgement, and it happens once
 * per company. Structured output is non-negotiable here, because a model that cannot
 * hold the schema produces broken extractions, not merely worse ones.
 */
class ContactExtractor extends EveilAgent implements HasStructuredOutput
{
    public static function requiresStrictStructure(): bool
    {
        return true;
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are given the text of a company's contact, team, about and legal pages.
        Extract the people and the email addresses that are actually written there.

        Copy addresses exactly as they appear. Do not repair, complete or invent one: a
        guessed address that bounces damages the sender's reputation, and a wrong name
        in an opening line is worse than no name at all. If a page writes an address to
        dodge scrapers, as in "jean (at) example dot be", return it in normal form, because
        it was still published.

        Separate people from the front desk. A named human with a role is worth far more
        to a salesperson than info@ or contact@, but the generic address is often the
        only way in at a small business, so return both.

        Owners, founders and managers matter most: in a small business they are the ones
        who decide. Note the role exactly as the page words it.

        If the pages show a personal address, say what shape it follows: first.last,
        firstinitiallast, first, and so on. That is what lets us reach the other people
        named on the site.
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'people' => $schema->array()->items($schema->object([
                'first_name' => $schema->string()->description('Empty when only a surname is given.')->required(),
                'last_name' => $schema->string()->description('Empty when unknown.')->required(),
                'title' => $schema->string()->description('Role as the page words it.')->required(),
                'email' => $schema->string()->description('Only if written on the page. Empty otherwise, and never guess.')->required(),
            ]))->description('Named humans. Empty when the site names nobody.')->required(),

            'generic_emails' => $schema->array()->items($schema->string())
                ->description('info@, contact@, hello@ and the like, exactly as written.')
                ->required(),

            'email_pattern' => $schema->string()
                ->description('Shape of the personal addresses seen: first.last, firstlast, f.last, first, or empty when none was shown.')
                ->required(),

            'phone' => $schema->string()->description('Main phone number, when given.')->required(),
        ];
    }
}
