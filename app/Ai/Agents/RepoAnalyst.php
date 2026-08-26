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
        package manifest, a changelog, whichever exist. Extract what they actually say,
        the way a technical co-founder reading the repo for the first time would.

        Be concrete. Name real dependencies, real commands, real file names when they
        matter. "Modern tech stack" is useless; "Laravel 13, PHP 8.5, Postgres, Redis" is
        what the manifest actually says.

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
            'tech_stack' => $schema->array()
                ->items($schema->string())
                ->description('Languages, frameworks and notable dependencies, as the manifest actually names them.')
                ->required(),

            'capabilities' => $schema->array()
                ->items($schema->string())
                ->description('Concrete things the repo demonstrably does: named in the README, a changelog entry, a directory that only exists for one purpose. Never marketing language.')
                ->required(),

            'notes' => $schema->string()
                ->description('Whatever a positioning writer would want but the product\'s own site likely never says: self-hostable, which databases or platforms it supports, an integration only visible in the dependency list. Empty string when there is nothing like that.')
                ->required(),

            'confidence' => $schema->integer()->min(0)->max(100)
                ->description('0-100. Would someone who reads code recognise this as accurate? Not whether the README happened to spell it out in those words.')
                ->required(),
        ];
    }
}
