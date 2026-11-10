<?php

namespace App\Ai\Agents;

use App\Enums\CompetitorSignal;
use App\Models\Project;
use App\Services\Reddit\OpportunityCandidate;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Stringable;

/**
 * The inverse of `RedditThreadTriage`: that one asks "is the author talking
 * about their OWN product" (a lead signal); this one asks "is this thread
 * worth THIS product replying to" (an engagement signal), for
 * `App\Jobs\ScanRedditOpportunities`.
 *
 * Batches BOTH of that job's discovery mechanisms in one call, distinguished
 * by `source` on each item:
 * - `subreddit_scan`: `text` is one live post or comment. For a post the
 *   question is "does the author describe a problem this product solves."
 *   A comment is shown under its own post (`parentPost`) and the question
 *   is whether a reply belongs under THAT comment: sometimes the comment is
 *   the better place (a follow-up question, a second person with the same
 *   need), sometimes the need is only the post's and the post is.
 * - `seo_thread`: `text` is a thread's title, selftext and a handful of its
 *   top comments - a thread that already ranks on Google for a buyer-intent
 *   search (`App\Services\Reddit\SeoThreadFinder`). The question is "is this
 *   a genuine buyer-intent thread, and are the existing answers weak,
 *   outdated, or simply missing a mention of this product" - judging the
 *   THREAD, not one author's problem.
 *
 * One shared batch is cheaper than two separate calls and the underlying
 * judgment - "would replying here genuinely help, with this product as the
 * reason" - is the same question either way.
 *
 * The same pass also asks three things a researcher notices while reading,
 * independently of whether a thread is worth a reply: whether it would make
 * a blog article (`article_angle`), whether it says something about one of
 * the named competitors (`competitor`, `competitor_signal`,
 * `competitor_summary`), and whether people ask for a capability this
 * product lacks (`feature_idea`, `feature_evidence`). All become `Insight`
 * rows in the Insights feed, at no extra model call.
 */
class RedditOpportunityTriage extends EveilAgent implements HasStructuredOutput
{
    /**
     * @param  Collection<int, OpportunityCandidate>  $items
     */
    public function __construct(Project $project, private Collection $items)
    {
        parent::__construct($project);
    }

    public static function smallModelSufficient(): bool
    {
        return true;
    }

    /**
     * An extraction task, not a generative one: a weak model here returns
     * plausible-looking verdicts about the wrong text, which is worse than
     * no verdict at all.
     */
    public static function requiresStrictStructure(): bool
    {
        return true;
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You decide, for each Reddit item below, whether replying there is a genuine
        opportunity for the product described - never whether the item merely mentions
        a related topic.

        Two kinds of item, told apart by their "source" line:

        - subreddit_scan: one live post or comment. For a post, genuine means the
          AUTHOR is describing a real problem, need or question this specific product
          solves - not a passing mention, not a topic that is merely adjacent. A
          generic discussion with no real problem stated is NOT an opportunity.

          A comment is shown under the post it replies to, and the reply you are
          judging would sit directly under THAT comment, answering its author. So the
          question is whether this comment is the right place to reply, not whether
          the post is. It is when the comment itself carries the need: its author
          shares the same problem, asks a follow-up, pushes back on an answer, or asks
          how to actually do what was suggested. It can also be when the comment
          recommends an approach this product carries out and a reply building on it
          would genuinely help both the commenter and the original asker. It is NOT
          when the comment is a complete answer that leaves nothing to add, or when
          the need is only the post's and replying under someone else's answer would
          just talk past them: the post itself is the place for that, and it is
          judged on its own. Write the reason about the comment and who the reply
          would address, never as if the comment were the post.
        - seo_thread: a thread's title, selftext and some of its top comments, already
          ranking on a search engine for a buyer-intent query (visible on its own
          "search_query" line, e.g. "best invoicing software" or "Notion alternative").
          Genuine means this is a real buyer-intent discussion (not a joke thread, not
          off-topic despite the query) AND the existing answers shown are weak, outdated,
          wrong for the asker, or simply never mention this product - so a specific,
          honest answer naming it would add real value. If the existing top answers
          already cover this product well, it is NOT an opportunity.

        Never call something an opportunity just because it is on-topic. The bar is
        "would a specific, honest, genuinely useful reply naming this product help the
        people who will read this thread" - not "could this product theoretically be
        mentioned here."

        Write the reason as one sentence, specific to what the item actually says,
        usable as-is to explain to the person approving drafts why this thread was
        picked.

        Separately, judge every item a second way, as someone who writes this
        product's blog would: is this a discussion worth turning into an article on
        the product's blog? That is a different question from the one above. A thread
        can be worth an article without being a reply opportunity (an interesting
        debate in the product's field, a question many people clearly share, a
        misconception worth correcting), and a reply opportunity is not automatically
        an article. When it is worth one, write the article's angle in one sentence:
        what the article would answer or argue, framed for this product's readers,
        not a summary of the thread. Otherwise leave article_angle empty. Most items
        are worth neither.

        Finally, as someone watching the market: does the item say something about
        one of the competitors named in the product portrait? Only an actual opinion
        or experience counts: a complaint about it, a comparison with another tool,
        someone leaving it or looking to, or praise. A bare name in a list is not.
        When it does, set competitor to that competitor's name exactly as the
        portrait spells it, competitor_signal to what it is, and competitor_summary to
        one sentence saying what users think of it here, specific enough to be read
        on its own. Otherwise leave competitor and competitor_summary empty and set
        competitor_signal to none.

        And as the founder deciding what to build next: do the people in this item ask
        for, or clearly miss, a capability this product does not have yet? Only a
        concrete capability counts, named as a developer would build it ("export
        invoices to accounting software", never "better integrations"), and only one
        the portrait does not already list. When it does, set feature_idea to that
        capability and feature_evidence to one sentence on what people say here that
        shows the need. Otherwise leave both empty. Most items have none.
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'items' => $schema->array()->items($schema->object([
                'permalink' => $schema->string()->description('Exactly as given to you.')->required(),
                'is_opportunity' => $schema->boolean()->description('True only when replying here would genuinely help, with this product as the reason.')->required(),
                'reason' => $schema->string()->description('One sentence, specific to this item, usable as the draft\'s evidence.')->required(),
                'article_angle' => $schema->string()->description('The blog article this discussion would make, in one sentence. Empty when it would not make one.')->required(),
                'competitor' => $schema->string()->description('The competitor from the portrait this item gives an opinion on, spelled as the portrait spells it. Empty when none.')->required(),
                'competitor_signal' => $schema->string()->enum(['none', ...array_column(CompetitorSignal::cases(), 'value')])->description('What the item says about that competitor. none when competitor is empty.')->required(),
                'competitor_summary' => $schema->string()->description('One sentence: what users think of that competitor here. Empty when competitor is empty.')->required(),
                'feature_idea' => $schema->string()->description('A capability people here ask for that this product lacks, named as a developer would build it. Empty when none.')->required(),
                'feature_evidence' => $schema->string()->description('One sentence: what people here say that shows the need. Empty when feature_idea is empty.')->required(),
            ]))->description('One entry per item you were given, none missing.')->required(),
        ];
    }

    public function triage(): StructuredAgentResponse
    {
        /** @var StructuredAgentResponse $response */
        $response = $this->prompt($this->buildPrompt());

        return $response;
    }

    private function buildPrompt(): string
    {
        $product = "## What this product is\n\n{$this->productPortrait()}";

        $lines = $this->items->map(function (OpportunityCandidate $item): string {
            $query = $item->searchQuery !== null ? "\nsearch_query: {$item->searchQuery}" : '';
            $subreddit = $item->subreddit !== null ? "r/{$item->subreddit}" : 'unknown subreddit';

            if ($item->parentPost !== null) {
                return "[{$item->permalink}] source: {$item->source->value}, a comment ({$subreddit})\n"
                    ."Under the post: {$item->parentPost}\n"
                    ."u/{$item->author} commented: {$item->text}";
            }

            return "[{$item->permalink}] source: {$item->source->value} ({$subreddit}){$query}\nu/{$item->author}: {$item->text}";
        })->implode("\n\n");

        return "{$product}\n\n## Items to judge\n\n{$lines}";
    }
}
