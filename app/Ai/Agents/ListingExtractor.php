<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Stringable;

/**
 * Reads a directory listing page and returns the businesses on it.
 *
 * The last rung of the harvest ladder, and the only one that costs money: it
 * runs when a page ships no usable JSON-LD. Runs on the cheap model, because this
 * is reading a list rather than judging it, and structured output is non-negotiable.
 *
 * It is given markdown, not text, which is the whole reason `HtmlText` emits
 * markdown: `[Acme Plumbing](/company/acme-plumbing-4412)` keeps the name and its
 * page together, where flat text gave two hundred names and two hundred URLs
 * with nothing joining them.
 */
class ListingExtractor extends EveilAgent implements HasStructuredOutput
{
    public static function smallModelSufficient(): bool
    {
        return true;
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are given one page of a business directory, as markdown. Return the
        businesses listed on it.

        A listing page repeats the same block: a name, usually linked to a detail page,
        sometimes an address, a phone number or a website. Return one entry per business.
        Copy what is written. Never complete an address, invent a website, or tidy a
        name into what you think it should be.

        What is NOT a business on this page: the directory itself, its categories, its
        navigation, its "popular searches", its legal pages, its advertisers' banners.
        A link is a business only when the page presents it as an entry in the list.

        Two different links pointing at the same business are one entry. The same
        business appearing in two categories is still one entry.

        Distinguish the two kinds of URL. `website` is the business's OWN site, on its
        own domain, so return it only when the page actually shows it. `detail_url` is its
        page inside this directory. Most entries have a detail_url and no website; that
        is expected and is not a reason to guess one.

        Return an empty list rather than a bad one. A page that turns out to be an
        article, a category index or a single business's own page has no listing on it.
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'businesses' => $schema->array()->items($schema->object([
                'name' => $schema->string()->description('As written on the page.')->required(),
                'website' => $schema->string()
                    ->description("The business's own site, on its own domain. Empty when the page does not show one, and never guess.")
                    ->required(),
                'detail_url' => $schema->string()
                    ->description('Its page inside this directory. Empty when the name is not linked.')
                    ->required(),
                'email' => $schema->string()->description('Only if written. Empty otherwise.')->required(),
                'phone' => $schema->string()->description('Only if written. Empty otherwise.')->required(),
                'address' => $schema->string()->description('Only if written. Empty otherwise.')->required(),
            ]))->description('Empty when the page carries no listing at all.')->required(),
        ];
    }
}
