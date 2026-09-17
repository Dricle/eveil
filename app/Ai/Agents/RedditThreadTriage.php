<?php

namespace App\Ai\Agents;

use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Stringable;

/**
 * Reads what `RedditSource`'s mechanical pass could not resolve a link for: a
 * post or a comment with no raw URL in it, or one whose only link is on the
 * denylist. A regex can tell "this text contains a URL" but not "this person
 * is talking about a product they built" - that is a reading-comprehension
 * question, so it gets the one thing a regex cannot do: an actual read.
 *
 * Batched, one call per probe, the same shape `ResultTriage` already uses for
 * hosts - cheaper and simpler than one call per item, and there is no reason
 * to cache it the way a directory page is cached: a given thread has no
 * reason to be read twice.
 */
class RedditThreadTriage extends EveilAgent implements HasStructuredOutput
{
    /**
     * @param  Collection<int, array{permalink: string, author: string, text: string}>  $items
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
     * plausible-looking verdicts about the wrong text, which is worse than no
     * verdict at all.
     */
    public static function requiresStrictStructure(): bool
    {
        return true;
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are given Reddit posts and comments that mention no usable link, or only a
        link to Reddit itself, an image host or a video host. Decide, for each one,
        whether the AUTHOR is talking about a product or business they are personally
        behind - not a tool they merely use, not a link they are sharing for someone
        else, not a question, not an answer that names somebody else's product.

        A genuine case reads like a founder or builder: "I made", "I built", "my app",
        "we launched", offering it, describing what it does, inviting people to try it
        or ask questions about it. It is NOT: recommending a tool someone else made,
        asking for tool recommendations, discussing news, or naming a company they have
        no stake in.

        When you judge it genuine, give the strongest identifying detail the text
        itself contains - the product's name if stated, otherwise a short description of
        what it does. Never invent a name or a URL the text does not contain: a
        candidate with a wrong identifier is worse than one left unresolved, because
        whatever you name is what gets searched for next.

        Write the reason as a sentence usable as-is to explain to someone why this
        showed up in their leads: specific to what the person actually wrote.
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
                'is_candidate' => $schema->boolean()->description('True only when the author is talking about their own product.')->required(),
                'product_identifier' => $schema->string()->description('The product name if stated, else a short description. Empty when not a candidate.')->required(),
                'reason' => $schema->string()->description('One sentence, specific to this text, usable as the lead\'s explanation.')->required(),
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
        $lines = $this->items->map(fn (array $item): string => "[{$item['permalink']}] u/{$item['author']}: {$item['text']}"
        )->implode("\n\n");

        return "Posts and comments to read:\n\n{$lines}";
    }
}
