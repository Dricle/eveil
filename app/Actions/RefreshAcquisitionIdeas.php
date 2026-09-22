<?php

namespace App\Actions;

use App\Ai\Agents\WebsiteAnalyst;
use App\Enums\AgentRunStatus;
use App\Models\AgentRun;
use App\Models\Project;
use App\Services\Discovery\SiteCrawler;

/**
 * A fresh site read that only ever writes `knowledge_base.recommendations` -
 * never `AnalyzeWebsite`'s full portrait. Deliberately does not check or set
 * `knowledge_base_edited_by_user`: that flag protects the portrait fields
 * from a re-analysis overwriting a hand correction (the user's own, or
 * Evie's via `UpdateKnowledgeBase`), and this action never touches them, so
 * the flag has nothing to say here either way.
 */
class RefreshAcquisitionIdeas
{
    public function __construct(private SiteCrawler $crawler) {}

    public function handle(Project $project, ?AgentRun $run = null): void
    {
        $pages = $this->crawler->crawl($project->url);

        if ($pages->isEmpty()) {
            $run?->update([
                'status' => AgentRunStatus::Failed,
                'error' => "Nothing could be read at {$project->url}: the site is unreachable, "
                    .'blocked by robots.txt, or renders entirely in JavaScript.',
            ]);

            return;
        }

        $agent = new WebsiteAnalyst($project, $pages);

        // The caller already opened a run row when it queued this: report
        // into it instead of leaving a `pending` row behind next to a second
        // one (same reasoning as `DeriveTargetProfiles::handle()`).
        if ($run !== null) {
            $agent->recordInto($run);
        }

        $response = $agent->analyze();

        $project->update([
            'knowledge_base' => [
                ...$project->knowledge_base ?? [],
                'recommendations' => $project->mergeRecommendations($response->structured['recommendations'] ?? []),
            ],
        ]);
    }
}
