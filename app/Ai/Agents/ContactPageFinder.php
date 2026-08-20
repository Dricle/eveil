<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Stringable;

/**
 * Picks which of a site's links lead to its contact details.
 *
 * The escape hatch for `PathHint`, and it only runs when the learned fragments
 * match nothing on a site, in a market whose word for "contact us" we have not
 * met yet. Whatever it picks is turned back into hints, so the next site in
 * that language costs nothing.
 *
 * Works off the markdown link list, which is why `HtmlText` emits markdown:
 * `[Chi siamo](/chi-siamo)` carries the label AND the path, and the label is
 * what makes an unfamiliar path readable.
 */
class ContactPageFinder extends EveilAgent implements HasStructuredOutput
{
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are given the links from a company's home page, as markdown. Return the ones
        that lead to pages where a human name, an email address, a postal address or a
        phone number would be published.

        Typically: contact, about, the team, legal notices, imprint, privacy. In whatever
        language the site is written in. That is the entire reason you are being asked
        rather than a keyword list.

        Judge the LABEL as much as the path. A link reading "Nous rencontrer" pointing at
        /nr is a contact page; one reading "Nos produits" pointing at /contact-lens is
        not. Ignore anything that is plainly a product, a blog post, a login, a basket or
        a social profile.

        Return at most five, best first, and return none rather than padding the list.
        A page that turns out to hold no contact details costs a fetch and teaches us the
        wrong word.
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'links' => $schema->array()->items($schema->object([
                'url' => $schema->string()->description('Exactly as it appeared in the list.')->required(),
                'why' => $schema->string()->description('A few words: what you expect to find there.')->required(),
            ]))->description('Empty when the site links to nothing of the sort.')->required(),
        ];
    }
}
