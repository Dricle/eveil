<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Stringable;

/**
 * Reads a handful of files out of a linked repo — a README, a package
 * manifest, a changelog — and extracts what they say about the product, so
 * the knowledge base is not built from marketing copy alone. Source code
 * often names capabilities the site never bothers to (self-hostable, which
 * databases it supports, an integration buried in a dependency list) and
 * never names ones the site oversells.
 */
class RepoAnalyst extends EveilAgent implements HasStructuredOutput
{
    public static function requiresStrictStructure(): bool
    {
        return true;
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are given a repository's metadata and a handful of its files: a README, a
        package manifest, a changelog, whichever exist. You are reading this for a sales
        agent, not an engineer vetting a dependency — they will use what you find to sell
        the product, not to build against it.

        Dependencies only matter when they say something a buyer would care about:
        self-hostable, which databases or platforms it runs on, an integration, a
        compliance-relevant library (billing, auth, search). Skip the rest of the
        manifest — nobody sells a deal on "uses Guzzle".

        The real find is what the code says that the marketing site doesn't: a feature
        buried in a directory name, a changelog entry for something never announced, an
        integration only visible in the dependency list. Look for that on purpose.

        Work only from what is here. Where a file is missing or thin, say so rather than
        inventing what a project like this "probably" has.
        PROMPT.$this->projectInstructions();
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'capabilities' => $schema->array()
                ->items($schema->string())
                ->description('What the product demonstrably does, phrased as a feature a buyer would recognise ("handles subscription billing", not "uses Laravel Cashier"). Named in the README, a changelog entry, a directory that only exists for one purpose. Never marketing language, never a bare dependency name.')
                ->required(),

            'hidden_features' => $schema->array()
                ->items($schema->string())
                ->description('Capabilities visible only in the code, that the product\'s own site likely never mentions: self-hostable, which databases or platforms it supports, an integration only visible in the dependency list, an admin/ops feature with no marketing page. This is the most valuable field for the sales agent reading this — look hard before leaving it empty.')
                ->required(),

            'tech_stack' => $schema->array()
                ->items($schema->string())
                ->description('Only the technologies that matter to a buyer\'s decision, not a full dependency dump: language/framework and version if the manifest states it, plus anything implying self-hosting, data residency, or platform compatibility. Leave out routine libraries.')
                ->required(),

            'confidence' => $schema->integer()->min(0)->max(100)
                ->description('0-100. Would someone who reads code recognise this as accurate? Not whether the README happened to spell it out in those words.')
                ->required(),
        ];
    }
}
