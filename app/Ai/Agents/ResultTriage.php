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
 *
 * Deliberately blind to the target profile, which is what makes the verdict
 * shareable at all. The question is what a host IS — one organisation, or a
 * list of them — never whether it suits a particular buyer. A newspaper is a
 * company somebody sells to; a job board is a list of companies that are
 * hiring, which is precisely what a recruitment agency hunts. Encode relevance
 * here and the answer stops being reusable across projects, which is the whole
 * point of the registry. Relevance belongs to qualification, per profile.
 */
class ResultTriage extends EveilAgent implements HasStructuredOutput
{
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are given search results, one line per host, with the page title, a snippet
        and how many of the results sit on that host. Say what each HOST is
        STRUCTURALLY. You are judging the site, not the single page.

        You are NOT judging whether the host is useful to anyone in particular. You do
        not know who is prospecting or what they sell, and you must not guess: a
        newspaper is a company that someone sells to, and a job board is a list of
        companies that are hiring, which is exactly what a recruitment agency hunts.
        Relevance is decided later, by someone who knows the target. Your answer is
        reused by every future search on this installation, so it has to hold for all of
        them.

        index — the host publishes lists of ORGANISATIONS. Directories, marketplaces,
        review sites, job boards, delivery platforms, startup showcases, professional
        registers, chambers of commerce, trade federations with a member list, code
        hosting that lists organisations. These are the most valuable answer: one page
        can name hundreds of businesses, many of which have no website of their own and
        appear nowhere else.

        entity — one organisation's own site. A company, a newspaper, a school, an
        agency, a public body. What we are ultimately looking for.

        social — a social network or profile platform. Structurally it may well list
        organisations, but we cannot read it: they block automated access and their
        terms forbid it.

        other — structurally NEITHER a single organisation nor a list of them: search
        engines, encyclopaedias, discussion forums, blog and publishing platforms,
        software documentation, package registries. This is not a verdict of
        worthlessness — an individual thread or article on such a host may well name
        real businesses — it only says the HOST is not itself a company or a directory.

        The count is your strongest clue for telling index from entity. A host holding
        twelve of twenty results is almost always an index — a real organisation appears
        once, for its own name, not across a whole result page. One result on a host
        says nothing either way; judge it on the title and the domain.

        The test for index is simple: would following this host give you MORE
        organisations? A company that happens to publish a blog does not qualify. A
        directory of products rather than of businesses does not qualify.

        When a host could plausibly be two things, prefer index: harvesting one that
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
                'kind' => $schema->string()->enum(['index', 'entity', 'social', 'other'])->required(),
                'reason' => $schema->string()->description('One short clause. Shown to an operator reviewing the registry.')->required(),
            ]))->description('One entry per host you were given, none missing.')->required(),
        ];
    }
}
