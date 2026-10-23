<?php

namespace App\Ai\Agents;

use App\Models\Project;
use App\Services\Reddit\TopComment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Stringable;

/**
 * Judges comments somebody ELSE wrote, already filtered on score by
 * `App\Actions\HarvestRedditReplyExamples`, and keeps the ones worth showing
 * `RedditReplyWriter` as a model of how a product mention survives a
 * subreddit.
 *
 * A different question from `RedditOpportunityTriage`'s, which reads the same
 * threads: that one asks whether WE should reply, this one asks whether a
 * stranger's reply is worth learning from. Score alone is a poor filter - a
 * one-line "just use X" scores well and teaches nothing, and so does a joke -
 * so the score decides what gets looked at and this decides what gets kept.
 *
 * Deliberately NOT given the product portrait: a good product mention is a
 * good product mention whatever the product, the bank is shared by every
 * project on the instance, and judging a stranger's comment against one
 * tenant's knowledge base would fill the pool for everyone else with whatever
 * that tenant happens to sell.
 */
class RedditExampleTriage extends EveilAgent implements HasStructuredOutput
{
    /**
     * @param  Collection<int, TopComment>  $comments
     */
    public function __construct(Project $project, private Collection $comments)
    {
        parent::__construct($project);
    }

    public static function smallModelSufficient(): bool
    {
        return true;
    }

    /**
     * A verdict per id, keyed back to the comment it judged: a model that
     * cannot hold the schema returns confident verdicts about the wrong
     * comment, and the row it writes goes into every project's prompt.
     */
    public static function requiresStrictStructure(): bool
    {
        return true;
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are shown Reddit comments other people wrote, each one already among the
        highest-scoring replies on its own thread. They are candidates for a library of
        examples shown to someone learning to mention their own product in a Reddit
        reply without being downvoted for it.

        Keep a comment only when ALL of these hold:

        - It names at least one real product, tool or service as part of an answer.
        - It reads as a person who actually used the thing, not as an advertisement, a
          press release or an obvious plant.
        - There is something to learn from HOW it is written: it gives a reason, a
          trade-off, a caveat, a comparison, or the context the recommendation applies
          in.

        Reject everything else, and reject generously. In particular:

        - A bare recommendation with no substance ("just use X", "X. next question").
        - A comment that mentions no product at all, however well it scored.
        - A pure complaint or rant about a product, with no useful recommendation.
        - A joke, a meme, or a comment whose score clearly came from being funny.
        - Anything that reads like marketing copy or an undisclosed promotion.

        Most comments you are shown will not be worth keeping. A small library of
        genuinely instructive examples is worth more than a large one, so err towards
        rejecting.

        Write the reason as one sentence naming what this comment specifically does
        well, or why it falls short, for a person auditing the library later.
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'items' => $schema->array()->items($schema->object([
                'id' => $schema->string()->description('Exactly as given to you.')->required(),
                'is_good_example' => $schema->boolean()->description('True only when the comment recommends a product AND there is something to learn from how it is written.')->required(),
                'reason' => $schema->string()->description('One sentence, specific to this comment, on what it does well or why it falls short.')->required(),
            ]))->description('One entry per comment you were given, none missing.')->required(),
        ];
    }

    public function judge(): StructuredAgentResponse
    {
        /** @var StructuredAgentResponse $response */
        $response = $this->prompt($this->buildPrompt());

        return $response;
    }

    private function buildPrompt(): string
    {
        $lines = $this->comments->map(function (TopComment $comment): string {
            $subreddit = $comment->subreddit !== null ? "r/{$comment->subreddit}" : 'unknown subreddit';

            return "[{$comment->id}] {$subreddit}, score {$comment->score}\nu/{$comment->author}: {$comment->body}";
        })->implode("\n\n");

        return "## Comments to judge\n\n{$lines}";
    }
}
