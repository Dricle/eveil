<?php

namespace App\Actions;

use App\Ai\Agents\ArticleTopicMatcher;
use App\Enums\AgentRunStatus;
use App\Enums\ArticleStatus;
use App\Enums\InsightKind;
use App\Enums\InsightStatus;
use App\Models\AgentRun;
use App\Models\Article;
use App\Models\Insight;
use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * Turns the article angles a Reddit scan proposed into topics. A thread
 * asking a question an open topic already holds is added to that topic and
 * counted, one already answered by an article or a page of the site is
 * dropped, and only a genuinely new question opens a topic.
 *
 * A dismissal sticks to its threads, never to the question: "Dismiss all"
 * clears a backlog rather than judging every topic in it, so a question
 * people start asking again in new threads comes back.
 *
 * Also forgets the topics nobody asked about again for a month, here on the
 * only write, so the backlog stays short without anyone clearing it.
 *
 * Runs inside `CurrentProject::run()`: every query below is project-scoped.
 */
class NoteArticleIdeas
{
    /**
     * Far more than a backlog that forgets its lone topics after a month
     * should hold, so in practice every open topic.
     */
    private const TOPIC_LIMIT = 150;

    private const ARTICLE_LIMIT = 100;

    private const BATCH = 15;

    /**
     * @param  Collection<int, array{angle: string, title: string, url: string, subreddit: string|null}>  $threads
     */
    public function handle(Project $project, Collection $threads): void
    {
        $threads = $threads
            ->unique('url')
            ->reject(fn (array $thread): bool => $this->alreadyNoted($thread['url']))
            ->values();

        // A long list asks the model to link angles among themselves, which
        // it does poorly; in batches, each one sees the topics the previous
        // ones opened and matches against real ids instead. A scan rarely
        // fills one batch: only a regrouping pays for several calls.
        foreach ($threads->chunk(self::BATCH) as $batch) {
            $this->note($project, $batch->values());
        }

        Insight::query()
            ->where('kind', InsightKind::Article)
            ->where('status', InsightStatus::Open)
            ->where('mentions', 1)
            ->where('created_at', '<', now()->subDays(Insight::LONE_TOPIC_DAYS))
            ->delete();
    }

    /**
     * @param  Collection<int, array{angle: string, title: string, url: string, subreddit: string|null}>  $threads
     */
    private function note(Project $project, Collection $threads): void
    {
        $topics = Insight::query()
            ->where('kind', InsightKind::Article)
            ->where('status', InsightStatus::Open)
            ->orderByDesc('mentions')
            ->latest('id')
            ->limit(self::TOPIC_LIMIT)
            ->get()
            ->keyBy('id');

        $articles = Article::query()
            ->where('status', '!=', ArticleStatus::Rejected)
            ->latest('id')
            ->limit(self::ARTICLE_LIMIT)
            ->pluck('title', 'id');

        $pages = $project->sitePages()->pluck('title')->filter()->values();

        $verdicts = $this->verdicts($project, $threads, $topics, $articles, $pages);

        /** @var array<int, Insight|null> $landedOn the topic each thread joined, null when it was dropped */
        $landedOn = [];

        foreach ($threads as $index => $thread) {
            $verdict = $verdicts->get($index);
            $ref = (int) ($verdict['ref'] ?? 0);

            if (($verdict['verdict'] ?? null) === 'article') {
                $landedOn[$index] = null;

                continue;
            }

            if (($verdict['verdict'] ?? null) === 'same_as' && $ref < $index && array_key_exists($ref, $landedOn)) {
                $topic = $landedOn[$ref];

                if ($topic === null) {
                    $landedOn[$index] = null;

                    continue;
                }
            } else {
                // An id the matcher made up, or no verdict at all, is a new
                // topic: better a duplicate than a good idea lost.
                $topic = ($verdict['verdict'] ?? null) === 'topic' ? $topics->get($ref) : null;
            }

            $landedOn[$index] = $topic === null ? $this->open($thread) : $this->join($topic, $thread);
        }
    }

    /**
     * One call per scan. Skipped when there is nothing to compare a single
     * angle with: the answer could only be "new".
     *
     * @param  Collection<int, array{angle: string, title: string, url: string, subreddit: string|null}>  $threads
     * @param  Collection<int, Insight>  $topics
     * @param  Collection<int, string>  $articles
     * @param  Collection<int, string>  $pages
     * @return Collection<int, array{verdict: string, ref: int}>
     */
    private function verdicts(Project $project, Collection $threads, Collection $topics, Collection $articles, Collection $pages): Collection
    {
        if ($threads->count() === 1 && $topics->isEmpty() && $articles->isEmpty() && $pages->isEmpty()) {
            return new Collection;
        }

        $run = AgentRun::create([
            'project_id' => $project->id,
            'agent' => ArticleTopicMatcher::slug(),
            'status' => AgentRunStatus::Pending,
        ]);

        $agent = new ArticleTopicMatcher(
            $project,
            $threads->pluck('angle'),
            $topics->map(fn (Insight $topic): string => $topic->angle),
            $articles,
            $pages,
        );

        $items = $agent->recordInto($run)->match()->structured['items'] ?? [];

        $verdicts = new Collection;

        foreach (is_array($items) ? $items : [] as $item) {
            if (is_array($item) && isset($item['index'])) {
                $verdicts->put((int) $item['index'], ['verdict' => (string) ($item['verdict'] ?? ''), 'ref' => (int) ($item['ref'] ?? 0)]);
            }
        }

        return $verdicts;
    }

    /**
     * Whether a topic, of any status, already counts this thread.
     */
    private function alreadyNoted(string $url): bool
    {
        return Insight::query()
            ->where('kind', InsightKind::Article)
            ->where(fn ($query) => $query
                ->where('source_ref', $url)
                ->orWhereJsonContains('details->threads', [['url' => $url]]))
            ->exists();
    }

    /**
     * @param  array{angle: string, title: string, url: string, subreddit: string|null}  $thread
     */
    private function open(array $thread): Insight
    {
        return Insight::create([
            'kind' => InsightKind::Article,
            'source' => 'reddit',
            'source_ref' => $thread['url'],
            'title' => $thread['title'],
            'angle' => $thread['angle'],
            'url' => $thread['url'],
            'details' => ['threads' => [$this->threadEntry($thread)]],
            'mentions' => 1,
            'status' => InsightStatus::Open,
        ]);
    }

    /**
     * @param  array{angle: string, title: string, url: string, subreddit: string|null}  $thread
     */
    private function join(Insight $topic, array $thread): Insight
    {
        $details = $topic->details ?? [];
        $details['threads'] = [...($details['threads'] ?? []), $this->threadEntry($thread)];

        $topic->update(['details' => $details, 'mentions' => $topic->mentions + 1]);

        return $topic;
    }

    /**
     * @param  array{angle: string, title: string, url: string, subreddit: string|null}  $thread
     * @return array{title: string, url: string, subreddit: string|null}
     */
    private function threadEntry(array $thread): array
    {
        return ['title' => $thread['title'], 'url' => $thread['url'], 'subreddit' => $thread['subreddit']];
    }
}
