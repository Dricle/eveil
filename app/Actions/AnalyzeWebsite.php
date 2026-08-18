<?php

namespace App\Actions;

use App\Ai\Agents\WebsiteAnalyst;
use App\Enums\AnalysisStatus;
use App\Enums\AnalysisType;
use App\Models\Project;
use App\Models\ProjectAnalysis;
use App\Services\Discovery\SiteCrawler;
use App\Support\ParsedPage;
use Illuminate\Support\Collection;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

/**
 * Crawl a project's site, then turn it into the knowledge base both agents work
 * from — derived once, never re-entered per agent.
 */
class AnalyzeWebsite
{
    /**
     * Characters of page text handed to the model. Roughly 15k tokens, which is
     * ~$0.08 of Opus input — the bounded-budget rule applied to the
     * one place where a big site could otherwise run the bill up unnoticed.
     */
    private const MAX_CHARS = 60_000;

    public function __construct(private SiteCrawler $crawler) {}

    public function handle(Project $project, ?int $maxPages = null): ProjectAnalysis
    {
        $analysis = ProjectAnalysis::create([
            'project_id' => $project->id,
            'type' => AnalysisType::Website,
            'status' => AnalysisStatus::Running,
        ]);

        $failures = [];

        // Written after every page, not at the end: a crawl runs for minutes,
        // and a screen with nothing on it cannot be told from a broken one.
        $pages = $this->crawler->crawl(
            $project->url,
            $maxPages,
            function (Collection $pages, array $crawlFailures, int $planned) use ($analysis, &$failures): void {
                $failures = $crawlFailures;

                $analysis->update([
                    'raw' => $this->pagesRead($pages, $planned),
                    'failures' => $crawlFailures,
                ]);
            },
        );

        if ($pages->isEmpty()) {
            $analysis->update([
                'status' => AnalysisStatus::Failed,
                'error' => $failures[0]['reason']
                    ?? "Nothing could be read at {$project->url}: the site is unreachable, "
                    .'blocked by robots.txt, or renders entirely in JavaScript.',
            ]);

            return $analysis;
        }

        try {
            /** @var StructuredAgentResponse $response */
            $response = (new WebsiteAnalyst($project))->prompt($this->prompt($project, $pages));
        } catch (Throwable $e) {
            $analysis->update(['status' => AnalysisStatus::Failed, 'error' => $e->getMessage()]);

            throw $e;
        }

        $analysis->update([
            // A crawl that lost pages still produces a knowledge base — the
            // status says the portrait was built from part of the site, and
            // `failures` says which part is missing.
            'status' => $failures === [] ? AnalysisStatus::Succeeded : AnalysisStatus::Partial,
            'summary' => $response->structured,
            'failures' => $failures,
        ]);

        $this->applyToProject($project, $response->structured, $pages);

        return $analysis->refresh();
    }

    /**
     * What the crawl has read so far, and how far it may go — the two numbers a
     * progress line is made of.
     *
     * @param  Collection<int, ParsedPage>  $pages
     * @return array{max_pages: int, pages: array<int, array{url: string, title: string, chars: int}>}
     */
    private function pagesRead(Collection $pages, int $planned): array
    {
        return [
            'max_pages' => $planned,
            'pages' => $pages->map(fn (ParsedPage $page): array => [
                'url' => $page->url,
                'title' => $page->title,
                'chars' => $page->length(),
            ])->all(),
        ];
    }

    /**
     * A hand-edited knowledge base outranks any later re-analysis:
     * the user corrected us once and should not have to do it again.
     *
     * @param  array<string, mixed>  $summary
     * @param  Collection<int, ParsedPage>  $pages
     */
    private function applyToProject(Project $project, array $summary, Collection $pages): void
    {
        if ($project->knowledge_base_edited_by_user) {
            return;
        }

        $project->update([
            'knowledge_base' => $summary,
            'default_language' => is_string($summary['language'] ?? null) && $summary['language'] !== ''
                ? mb_substr($summary['language'], 0, 2)
                : $pages->first()?->language,
        ]);
    }

    /**
     * @param  Collection<int, ParsedPage>  $pages
     */
    private function prompt(Project $project, Collection $pages): string
    {
        $budget = self::MAX_CHARS;
        $sections = [];

        foreach ($pages as $page) {
            if ($budget <= 0) {
                break;
            }

            $text = mb_substr($page->text, 0, $budget);
            $budget -= mb_strlen($text);

            $sections[] = "## {$page->title}\nURL: {$page->url}\n\n{$text}";
        }

        $body = implode("\n\n---\n\n", $sections);

        return "Website of {$project->name} ({$project->url}).\n\n{$body}";
    }
}
