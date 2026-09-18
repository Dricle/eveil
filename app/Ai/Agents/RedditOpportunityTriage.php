<?php

namespace App\Ai\Agents;

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
 * - `subreddit_scan`: `text` is one live post or comment. The question is
 *   "does the author describe a problem this product solves."
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

        - subreddit_scan: one live post or comment. Genuine means the AUTHOR is
          describing a real problem, need or question this specific product solves -
          not a passing mention, not a topic that is merely adjacent. A generic
          discussion with no real problem stated is NOT an opportunity.
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
        $knowledgeBase = $this->project->knowledge_base ?? [];
        $competitors = empty($knowledgeBase['competitors'])
            ? ''
            : ' Named competitors: '.implode(', ', $knowledgeBase['competitors']).'.';

        $product = "## What this product is\n\n"
            .($knowledgeBase['what_it_does'] ?? 'Not analyzed yet.').$competitors;

        $lines = $this->items->map(function (OpportunityCandidate $item): string {
            $query = $item->searchQuery !== null ? "\nsearch_query: {$item->searchQuery}" : '';
            $subreddit = $item->subreddit !== null ? "r/{$item->subreddit}" : 'unknown subreddit';

            return "[{$item->permalink}] source: {$item->source->value} ({$subreddit}){$query}\nu/{$item->author}: {$item->text}";
        })->implode("\n\n");

        return "{$product}\n\n## Items to judge\n\n{$lines}";
    }
}
