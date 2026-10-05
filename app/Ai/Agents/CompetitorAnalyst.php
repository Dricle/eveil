<?php

namespace App\Ai\Agents;

use App\Models\Project;
use App\Support\ParsedPage;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Stringable;

/**
 * Reads the sites of the competitors the knowledge base names and lists the
 * capabilities they sell that this product does not. Same proposed/done/
 * archived lifecycle as the Website agent's acquisition ideas (ADR-032),
 * and the same "evidence or nothing" rule: a gap is only written when a
 * competitor's own page says they have it.
 */
class CompetitorAnalyst extends EveilAgent implements HasStructuredOutput
{
    /**
     * Characters of competitor page text handed to the model, all
     * competitors together. Same budget as `WebsiteAnalyst`, for the same
     * reason.
     */
    private const MAX_CHARS = 60_000;

    /**
     * @param  array<string, Collection<int, ParsedPage>>  $pagesByCompetitor  Already-crawled pages,
     *                                                                         keyed by competitor name.
     */
    public function __construct(Project $project, private array $pagesByCompetitor)
    {
        parent::__construct($project);
    }

    public function instructions(): Stringable|string
    {
        return <<<PROMPT
        You compare one product against its competitors and list the capabilities
        competitors sell that this product plainly does not have. The founder reads
        this to decide what to build next, so a wrong entry wastes real engineering
        time.

        The product:
        {$this->productPortrait()}

        You are given the text of a few pages from each competitor's website. Work only
        from that text and the portrait above. A gap is only a gap when a competitor's
        page names the capability AND nothing in the portrait or the repository digest
        says this product already has it. When unsure whether the product has it,
        leave it out: proposing something it already does makes the whole list look
        careless.

        Name capabilities, not marketing: "bulk CSV import of contacts", never "better
        onboarding". Ignore anything outside what this product is for: a competitor's
        unrelated side product is not a gap. Prefer what several competitors share over
        what one alone has, and say which competitors have it.

        Write every field in {$this->language()}.
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'recommendations' => $schema->array()
                ->items($schema->object([
                    'key' => $schema->string()
                        ->description(
                            'Stable identifier for this capability, English snake_case prefixed with feature_: '
                            .'feature_csv_import, feature_zapier_integration. A later run finding the same gap must '
                            .'reuse the same key.'
                        )
                        ->required(),
                    'idea' => $schema->string()
                        ->description('The missing capability, named as a developer would build it.')
                        ->required(),
                    'evidence' => $schema->string()
                        ->description('Which competitors offer it and what their page says. Never left empty or generic.')
                        ->required(),
                    'impact' => $schema->string()->enum(['high', 'medium', 'low'])
                        ->description('How much its absence plausibly costs in deals, given who this product is for.')
                        ->required(),
                    'effort' => $schema->string()->enum(['high', 'medium', 'low'])
                        ->description('How much building it would plausibly take.')
                        ->required(),
                ]))
                ->description(
                    'Capabilities competitors offer that this product lacks, ranked by impact and effort. '
                    .'At most eight. Empty when nothing concrete is missing.'
                )
                ->required(),
        ];
    }

    public function analyze(): StructuredAgentResponse
    {
        /** @var StructuredAgentResponse $response */
        $response = $this->prompt($this->buildPrompt());

        return $response;
    }

    private function language(): string
    {
        return $this->project->default_language ?? 'the language of the portrait';
    }

    private function buildPrompt(): string
    {
        // Split evenly up front: the first competitor's long pricing page
        // must not starve the last one out of the prompt entirely.
        $perCompetitor = intdiv(self::MAX_CHARS, max(1, count($this->pagesByCompetitor)));
        $sections = [];

        foreach ($this->pagesByCompetitor as $competitor => $pages) {
            $budget = $perCompetitor;
            $pageSections = [];

            foreach ($pages as $page) {
                if ($budget <= 0) {
                    break;
                }

                $text = mb_substr($page->text, 0, $budget);
                $budget -= mb_strlen($text);

                $pageSections[] = "### {$page->title}\nURL: {$page->url}\n\n{$text}";
            }

            $sections[] = "## Competitor: {$competitor}\n\n".implode("\n\n", $pageSections);
        }

        return implode("\n\n---\n\n", $sections).$this->repoDigest();
    }

    /**
     * Source code often shows a capability the site never mentions, and a
     * gap the product already closed in code is not a gap.
     */
    private function repoDigest(): string
    {
        $repositories = $this->project->knowledge_base['repositories'] ?? null;

        $capabilities = collect(is_array($repositories) ? $repositories : [])
            ->flatMap(fn (mixed $repo): array => is_array($repo) ? [...$repo['capabilities'] ?? [], ...$repo['hidden_features'] ?? []] : [])
            ->filter(fn (mixed $capability): bool => is_string($capability))
            ->implode('; ');

        return $capabilities === '' ? '' : "\n\n---\n\n## This product's repository also shows\n\n{$capabilities}";
    }
}
