<?php

namespace App\Actions;

use App\Ai\Agents\WebsiteAnalyst;
use App\Enums\AnalysisStatus;
use App\Enums\AnalysisType;
use App\Enums\Channel;
use App\Models\Project;
use App\Models\ProjectAnalysis;
use App\Services\Discovery\SiteCrawler;
use App\Support\ParsedPage;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Crawl a project's site, then turn it into the knowledge base both agents work
 * from: derived once, never re-entered per agent.
 */
class AnalyzeWebsite
{
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
            $response = (new WebsiteAnalyst($project, $pages))->analyze();
        } catch (Throwable $e) {
            $analysis->update(['status' => AnalysisStatus::Failed, 'error' => $e->getMessage()]);

            throw $e;
        }

        $analysis->update([
            // A crawl that lost pages still produces a knowledge base. The
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
     * What the crawl has read so far, and how far it may go. The two numbers a
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

        // The first reading switches the channels on, a later one only
        // updates the reasons: by then the toggles are the user's.
        if ($project->knowledge_base === null) {
            $project->fill($this->recommendedChannels($project, $summary['channel_fit'] ?? []));
        }

        $project->update([
            'knowledge_base' => [
                ...$summary,
                // Not this run's to know about: repos are read on their own
                // schedule (`ExploreRepo`), and a website re-analysis that
                // dropped them would silently undo that work.
                'repositories' => $project->knowledge_base['repositories'] ?? [],
                'gaps' => $this->mergeOpenQuestions($project, $summary['gaps'] ?? []),
                'recommendations' => $project->mergeRecommendations($summary['recommendations'] ?? []),
            ],
            'default_language' => is_string($summary['language'] ?? null) && $summary['language'] !== ''
                ? mb_substr($summary['language'], 0, 2)
                : $pages->first()?->language,
        ]);
    }

    /**
     * Every channel the user has not touched yet, switched to whether the
     * analysis recommended it. Email included: a product the analysis sees no
     * cold-email buyers for starts with the lead search paused, and one click
     * turns it back on.
     *
     * "Not touched" is "still in its default state", read fresh: the reading
     * takes minutes, and a channel switched from Settings or its own queue in
     * the meantime is the user's choice, not this run's to overwrite.
     *
     * @return array<string, mixed>
     */
    private function recommendedChannels(Project $project, mixed $fit): array
    {
        $keys = collect(is_array($fit) ? $fit : [])
            ->filter(fn (mixed $r): bool => is_array($r) && isset($r['channel']) && ($r['recommended'] ?? false) === true)
            ->pluck('channel');

        // No recommendation at all is no opinion, not "none of them": a thin
        // site must not leave a project with every channel off.
        if ($keys->isEmpty()) {
            return [];
        }

        $current = $project->fresh() ?? $project;

        return collect(Channel::cases())
            ->filter(fn (Channel $channel): bool => $channel->isEnabledFor($current) === $channel->enabledByDefault())
            ->flatMap(fn (Channel $channel): array => $channel->isEnabledFor($current) === $keys->contains($channel->value)
                ? []
                : $channel->attributes($keys->contains($channel->value)))
            ->all();
    }

    /**
     * The questions a fresh reading asks, with the answers already given kept
     * against them.
     *
     * Identity is the key, never the wording: a second reading that rephrases
     * the same question would otherwise ask it again, and the user has already
     * answered it once. An answered question the site now covers is kept too,
     * since what the user told us is knowledge in its own right, and nothing
     * else records it.
     *
     * @return list<array{key: string, question: string, answer: string|null}>
     */
    private function mergeOpenQuestions(Project $project, mixed $gaps): array
    {
        $answered = collect($project->openQuestions())
            ->filter(fn (array $question): bool => $question['answer'] !== null)
            ->keyBy('key');

        $asked = collect(is_array($gaps) ? $gaps : [])
            ->filter(fn (mixed $gap): bool => is_array($gap) && isset($gap['key'], $gap['question']))
            ->map(fn (array $gap): array => [
                'key' => (string) $gap['key'],
                'question' => (string) $gap['question'],
                'answer' => $answered[$gap['key']]['answer'] ?? null,
            ])
            ->keyBy('key');

        return array_values($answered->reject(fn (array $question): bool => $asked->has($question['key']))
            ->merge($asked)
            ->all());
    }
}
