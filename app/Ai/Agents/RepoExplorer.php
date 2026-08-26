<?php

namespace App\Ai\Agents;

use App\Ai\Tools\ListRepoPaths;
use App\Ai\Tools\ReadRepoFile;
use App\Models\Project;
use App\Services\RepoReader;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Stringable;

/**
 * `RepoAnalyst`'s expensive sibling: instead of a fixed set of priority
 * files chosen upfront, this one roams the repo itself, deciding what to
 * open and asking for more until it is done. Manual and priced accordingly
 * (`CreditPrice::current('repo-explorer')`) — nobody pays this by linking a
 * repo, only by asking for it.
 *
 * 40 steps is the real budget: each `ListRepoPaths`/`ReadRepoFile` call is
 * one, so this bounds the cost of one run the way `discovery.max_pages`
 * bounds a discovery run, not a setting since nobody asked to tune it.
 */
#[MaxSteps(40)]
class RepoExplorer extends EveilAgent implements HasStructuredOutput, HasTools
{
    /**
     * @param  Collection<int, string>  $paths  every path in the repo,
     *                                          fetched once so navigating never costs another GitHub call
     */
    public function __construct(
        Project $project,
        private RepoReader $reader,
        private string $owner,
        private string $repo,
        private string $branch,
        private Collection $paths,
        private ?string $githubToken = null,
    ) {
        parent::__construct($project);
    }

    public static function requiresStrictStructure(): bool
    {
        return true;
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are a senior engineer doing due diligence on a repository you have
        never seen, the way you would before deciding whether to depend on it.
        You decide which files to open: list a directory to see what is in it,
        read whatever a README or manifest points you toward, and keep going
        until you have actually seen enough to back every claim below. A
        small repo might need a handful of files, a large one dozens — read
        what the repo actually needs, not a fixed number.

        Be concrete. Name real dependencies, real commands, real file names.
        "Modern tech stack" is useless; "Laravel 13, PHP 8.4, Postgres, Redis"
        is what a manifest actually says.

        Work only from what you read. Where something is missing or thin, say
        so rather than inventing what a project like this "probably" has.
        PROMPT.$this->projectInstructions();
    }

    /**
     * @return array<int, object>
     */
    public function tools(): iterable
    {
        return [
            new ListRepoPaths($this->paths),
            new ReadRepoFile($this->reader, $this->owner, $this->repo, $this->branch, $this->githubToken),
        ];
    }

    /**
     * Same fields `RepoAnalyst::schema()` returns, plus `files_read`: this
     * agent's own record of what it actually opened, since nothing upstream
     * chose that list for it.
     *
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
                ->description('0-100. Would someone who reads code recognise this as accurate? Not whether a file happened to spell it out in those words.')
                ->required(),

            'files_read' => $schema->array()
                ->items($schema->string())
                ->description('Every file path you actually opened with the read tool, in the order you read them.')
                ->required(),
        ];
    }
}
