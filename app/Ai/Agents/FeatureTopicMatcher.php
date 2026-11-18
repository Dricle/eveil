<?php

namespace App\Ai\Agents;

use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Stringable;

/**
 * Keeps the missing features a list of capabilities rather than a list of
 * threads, for `App\Actions\NoteFeatureIdeas`. Given the capabilities the
 * Reddit scan just noted, it says for each one whether it is new, the same
 * as a feature already noted (open, built or turned down), or the same as
 * another one in the same batch.
 *
 * Names only, never the product portrait: the question is "same capability
 * or not", which the triage already answered against the portrait.
 */
class FeatureTopicMatcher extends EveilAgent implements HasStructuredOutput
{
    /**
     * @param  Collection<int, string>  $requests  the new capabilities, keyed by their index in the batch
     * @param  Collection<int, string>  $features  features already noted, keyed by insight id
     */
    public function __construct(
        Project $project,
        private Collection $requests,
        private Collection $features,
    ) {
        parent::__construct($project);
    }

    public static function smallModelSufficient(): bool
    {
        return true;
    }

    /**
     * Every verdict points at an id; a weak model that invents one merges a
     * thread into the wrong feature or drops a real need.
     */
    public static function requiresStrictStructure(): bool
    {
        return true;
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You keep a product's list of feature requests free of duplicates. Each new
        request below is one capability someone asked for. For each, decide which of
        these it is:

        - feature: a feature already noted is the same capability. ref is its id.
        - same_as: another NEW request earlier in this list is the same capability.
          ref is that request's index, always lower than this one's.
        - new: none of the above. ref is 0.

        Same capability means building one would satisfy both, even when the wording,
        the audience or the example differs. "Weekly tracking of brand mentions across
        ChatGPT and Perplexity for buyer prompts" and "Check whether AI search engines
        cite my brand for my category" are the same capability. "Filter prospects by
        hiring signals" and "Filter prospects by ad spend" are not: each needs its own
        data source.

        Before each verdict, write in reason the one capability both ask for, in a
        few words, or why nothing matches. When you cannot name a capability that
        building one would fully deliver for the other, the answer is new. Most
        requests are new: a shared channel or a shared word is not the same need.

        Prefer feature over same_as when both match.
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'items' => $schema->array()->items($schema->object([
                'index' => $schema->integer()->description('The new request\'s index, exactly as given.')->required(),
                'reason' => $schema->string()->description('A few words: the capability both ask for, or why nothing matches. Written before the verdict.')->required(),
                'verdict' => $schema->string()->enum(['new', 'feature', 'same_as'])->required(),
                'ref' => $schema->integer()->description('The feature id for feature, the earlier index for same_as, 0 for new.')->required(),
            ]))->description('One entry per new request, none missing.')->required(),
        ];
    }

    public function match(): StructuredAgentResponse
    {
        /** @var StructuredAgentResponse $response */
        $response = $this->prompt($this->buildPrompt());

        return $response;
    }

    private function buildPrompt(): string
    {
        $list = fn (Collection $items): string => $items->isEmpty()
            ? '(none)'
            : $items->map(fn (string $text, int $id): string => "[{$id}] {$text}")->implode("\n");

        return implode("\n\n", [
            "## Features already noted\n\n".$list($this->features),
            "## New requests to decide\n\n".$list($this->requests),
        ]);
    }
}
