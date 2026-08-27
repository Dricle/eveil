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
 * Reads a linked repo by roaming it itself, deciding what to open and
 * asking for more until it is done, rather than a fixed set of priority
 * files chosen upfront. Priced accordingly
 * (`CreditPrice::current('repo-explorer')`) — the frontend confirms the
 * cost before either linking a repo or retrying one triggers this.
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
        You are doing due diligence on a repository you have never seen, for a
        sales agent, not an engineer deciding whether to depend on it — what you
        find will be used to sell the product, not to build against it. You
        decide which files to open: list a directory to see what is in it, read
        whatever a README or manifest points you toward, and keep going until
        you have actually seen enough to back every claim below. A small repo
        might need a handful of files, a large one dozens — read what the repo
        actually needs, not a fixed number.

        Dependencies only matter when they say something a buyer would care
        about: self-hostable, which databases or platforms it runs on, an
        integration, a compliance-relevant library (billing, auth, search).
        Skip the rest of the manifest.

        The real find is what the code says that the marketing site doesn't: a
        feature buried in a directory name, a changelog entry for something
        never announced, an integration only visible in the dependency list.
        Look for that on purpose — it's why this agent is worth its price over
        the cheap fixed-file read.

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
     * `files_read` is this agent's own record of what it actually opened,
     * since nothing upstream chose that list for it.
     *
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
                ->description('0-100. Would someone who reads code recognise this as accurate? Not whether a file happened to spell it out in those words.')
                ->required(),

            'files_read' => $schema->array()
                ->items($schema->string())
                ->description('Every file path you actually opened with the read tool, in the order you read them.')
                ->required(),
        ];
    }
}
