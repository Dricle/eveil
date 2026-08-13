<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Stringable;

/**
 * Sorts a batch of search results into what each host actually IS.
 *
 * This replaced a hand-written blocklist of aggregator domains, which could
 * never be complete — Pages d'Or, Product Hunt, BetaList, Clutch, every trade
 * directory in every country — and, worse, was throwing away the most valuable
 * results. A directory's page for one trade in one town is not a company, it is
 * two hundred companies, and for a business with no site of its own it is the
 * only place an address is published.
 *
 * Judged per HOST, not per URL, and the verdict is remembered instance-wide, so
 * this runs once per host ever rather than once per search.
 */
class ResultTriage extends EveilAgent implements HasStructuredOutput
{
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are given search results, one line per host, with the page title, a snippet
        and how many of the results sit on that host. Say what each HOST is. You are
        judging the site, not the single page.

        index — the host publishes lists of businesses. Directories, marketplaces,
        review sites, "top 10" roundups, startup showcases, professional registers,
        chambers of commerce, trade federations with a member list. These are the most
        valuable answer: one page of a directory can name hundreds of businesses, many
        of which have no website and appear nowhere else.

        entity — one organisation's own site. What we are looking for.

        social — a social network or a profile platform. Never one company's site, and
        not something we can read.

        noise — everything else: encyclopaedias, news, forums, blog platforms, job
        boards, general marketplaces, search engines, government portals that publish no
        business list, software documentation.

        The count is your strongest clue. A host holding twelve of twenty results is
        almost always an index — a real business appears once, for its own name, not
        across a whole result page. One result on a host says nothing either way; judge
        it on the title and the domain.

        Do not confuse a company that happens to publish a blog list, or a directory of
        products rather than of businesses, with an index of BUSINESSES. The test is
        whether following the host would give you more companies to contact.

        When a host could plausibly be two things, prefer `index`: harvesting one that
        turns out to be a single company costs one wasted page, while discarding a real
        directory loses every business on it.
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'hosts' => $schema->array()->items($schema->object([
                'host' => $schema->string()->description('Exactly as given to you.')->required(),
                'kind' => $schema->string()->enum(['index', 'entity', 'social', 'noise'])->required(),
                'reason' => $schema->string()->description('One short clause. Shown to an operator reviewing the registry.')->required(),
            ]))->description('One entry per host you were given, none missing.')->required(),
        ];
    }
}
