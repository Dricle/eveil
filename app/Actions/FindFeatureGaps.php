<?php

namespace App\Actions;

use App\Ai\Agents\CompetitorAnalyst;
use App\Enums\AgentRunStatus;
use App\Enums\InsightKind;
use App\Enums\InsightStatus;
use App\Enums\RecommendationKind;
use App\Models\AgentRun;
use App\Models\Project;
use App\Services\Discovery\SiteCrawler;
use App\Services\Discovery\WebsiteFinder;

/**
 * Reads the sites of the competitors the knowledge base names, plus what
 * Redditors said about them (the competitor mentions the Reddit scan noted),
 * and writes the capabilities they have and this product lacks as `feature`
 * recommendations. Same replace as `RefreshAcquisitionIdeas`: a decided gap
 * is never rewritten or proposed again (ADR-032).
 */
class FindFeatureGaps
{
    /**
     * Competitors read per run, and pages read per competitor. Bounded on
     * purpose: each one is a search, a classification and a crawl.
     */
    private const MAX_COMPETITORS = 3;

    private const PAGES_PER_COMPETITOR = 5;

    /**
     * Latest Reddit mentions handed over, all competitors together: one line
     * each, so a few dozen cost little and say plenty.
     */
    private const MAX_MENTIONS = 40;

    public function __construct(private WebsiteFinder $websiteFinder, private SiteCrawler $crawler) {}

    public function handle(Project $project, ?AgentRun $run = null): void
    {
        $pagesByCompetitor = [];
        $competitors = collect(is_array($project->knowledge_base['competitors'] ?? null) ? $project->knowledge_base['competitors'] : [])
            ->filter(fn (mixed $name): bool => is_string($name) && trim($name) !== '')
            ->map(fn (string $name): string => trim($name))
            ->take(self::MAX_COMPETITORS);

        foreach ($competitors as $competitor) {
            // The category narrows the search the way an address does for a
            // registry record: "Notion" alone is a word, "Notion" + "note-taking
            // app" is a company.
            $url = $this->websiteFinder->find($competitor, (string) ($project->knowledge_base['product_category'] ?? ''), $project);
            $pages = $url === null ? collect() : $this->crawler->crawl($url, self::PAGES_PER_COMPETITOR);

            if ($pages->isNotEmpty()) {
                $pagesByCompetitor[$competitor] = $pages;
            }
        }

        $mentions = $project->insights()
            ->where('kind', InsightKind::CompetitorMention)
            ->where('status', '!=', InsightStatus::Dismissed)
            ->latest('id')
            ->limit(self::MAX_MENTIONS)
            ->get();

        if ($pagesByCompetitor === [] && $mentions->isEmpty()) {
            $run?->update([
                'status' => AgentRunStatus::Failed,
                'error' => 'No competitor site could be found or read. Name competitors in the knowledge base first.',
            ]);

            return;
        }

        $agent = new CompetitorAnalyst($project, $pagesByCompetitor, $mentions);

        if ($run !== null) {
            $agent->recordInto($run);
        }

        $response = $agent->analyze();

        $project->replaceOpenRecommendations($response->structured['recommendations'] ?? [], RecommendationKind::Feature);
    }
}
