<?php

namespace App\Jobs;

use App\Ai\Agents\ArticleWriter;
use App\Enums\AgentRunStatus;
use App\Enums\ArticleSourceType;
use App\Enums\ArticleStatus;
use App\Enums\InsightKind;
use App\Enums\InsightStatus;
use App\Enums\OutreachStatus;
use App\Models\AgentRun;
use App\Models\Article;
use App\Models\Company;
use App\Models\Insight;
use App\Models\Project;
use App\Notifications\ArticleDrafted;
use App\Services\Linkedin\NewsSearch;
use App\Support\CurrentProject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * One article: gather every signal Eveil already has, ask `ArticleWriter`
 * once, keep the draft. Same shape as `GenerateSocialPost`.
 *
 * `$brief` is set when Evie queued it from a conversation ("we just shipped
 * X"), `$ideaId` when the user clicked "Write it" on an idea: the article is
 * then about that, and nobody gets an email about it, since the user asked
 * for this one.
 */
class GenerateArticle implements ShouldQueue
{
    use Queueable;

    private const IDEA_LIMIT = 10;

    /** Enough threads to write from, without a popular topic filling the prompt. */
    private const THREADS_PER_IDEA = 5;

    /**
     * @param  int|null  $ideaId  the one idea to write, when the user clicked
     *                            "Write it" on it: it then becomes the brief.
     */
    public function __construct(public Project $project, public ?string $brief = null, public ?int $ideaId = null)
    {
        $this->onQueue('ai');
    }

    public function handle(NewsSearch $newsSearch, CurrentProject $currentProject): void
    {
        $currentProject->run($this->project, function () use ($newsSearch): void {
            $idea = $this->ideaId === null ? null : Insight::query()->where('status', InsightStatus::Open)->find($this->ideaId);

            if ($this->ideaId !== null && $idea === null) {
                return;
            }

            $brief = $idea === null
                ? $this->brief
                : "Write the article these Reddit discussions ask for.\nAngle: {$idea->angle}\nThreads:\n".collect($idea->threads())
                    ->map(fn (array $thread): string => "- {$thread['title']} ({$thread['url']})")
                    ->implode("\n");

            $clientWon = $this->pendingClientWin();

            $run = AgentRun::create([
                'project_id' => $this->project->id,
                'agent' => ArticleWriter::slug(),
                'status' => AgentRunStatus::Pending,
            ]);

            $agent = new ArticleWriter(
                $this->project,
                $this->project->sitePages(),
                Article::query()->where('status', '!=', ArticleStatus::Rejected)->latest('id')->limit(50)->get(['title']),
                Article::query()->where('status', ArticleStatus::Rejected)->latest('updated_at')->limit(5)->get(['title', 'rejection_reason']),
                $brief === null ? $this->openIdeas() : new Collection,
                $brief === null ? $newsSearch->recent($this->project) : new Collection,
                $brief === null ? $clientWon : null,
                $brief,
            );

            $structured = $agent->recordInto($run)->write()->structured;

            $body = trim((string) ($structured['body'] ?? ''));

            if ($body === '') {
                return;
            }

            // Never trust the model with where a clicked idea came from.
            $sourceType = $idea !== null
                ? ArticleSourceType::RedditThread
                : ArticleSourceType::tryFrom((string) ($structured['source_type'] ?? '')) ?? ArticleSourceType::AgentChoice;

            $sourceRef = match (true) {
                $idea !== null => $idea->source_ref,
                $sourceType === ArticleSourceType::ClientWon => (string) $clientWon?->id,
                default => trim((string) ($structured['source_ref'] ?? '')) ?: null,
            };

            $article = Article::create([
                'project_id' => $this->project->id,
                'agent_run_id' => $run->id,
                'source_type' => $sourceType,
                'source_ref' => $sourceRef,
                'evidence' => (string) ($structured['evidence'] ?? ''),
                'title' => (string) ($structured['title'] ?? ''),
                'meta_description' => (string) ($structured['meta_description'] ?? '') ?: null,
                'body' => $body,
                'language' => $this->project->default_language,
                'status' => ArticleStatus::Draft,
            ]);

            if ($sourceType === ArticleSourceType::RedditThread && $sourceRef !== null) {
                Insight::query()
                    ->where('kind', InsightKind::Article)
                    ->where('status', InsightStatus::Open)
                    ->where(fn ($query) => $query
                        ->where('source_ref', $sourceRef)
                        ->orWhereJsonContains('details->threads', [['url' => $sourceRef]]))
                    ->update(['status' => InsightStatus::Used, 'article_id' => $article->id]);
            }

            // Only the cadence emails: a brief or a clicked idea means the
            // user asked for this one and is waiting for it.
            if ($brief === null) {
                Notification::send($this->project->notifiableUsers(), ArticleDrafted::for($this->project));
            }
        });
    }

    /**
     * The open article topics, the most asked first: what the cadence picks
     * from. A topic many threads ask about answers real demand, and gives the
     * writer more than one discussion to draw on.
     *
     * @return Collection<int, array{angle: string, mentions: int, threads: list<array{title: string, url: string, subreddit: string|null}>}>
     */
    private function openIdeas(): Collection
    {
        return Insight::query()
            ->where('kind', InsightKind::Article)
            ->where('status', InsightStatus::Open)
            ->orderByDesc('mentions')
            ->latest('id')
            ->limit(self::IDEA_LIMIT)
            ->get()
            ->map(fn (Insight $idea): array => [
                'angle' => $idea->angle,
                'mentions' => $idea->mentions,
                'threads' => array_slice($idea->threads(), 0, self::THREADS_PER_IDEA),
            ]);
    }

    /**
     * The oldest Won company no article is about yet, same rule as
     * `GenerateSocialPost::pendingClientWin()`.
     */
    private function pendingClientWin(): ?Company
    {
        $used = Article::query()
            ->where('source_type', ArticleSourceType::ClientWon)
            ->pluck('source_ref')
            ->filter()
            ->map(fn (string $ref): int => (int) $ref);

        return Company::query()
            ->where('status', OutreachStatus::Won)
            ->whereNotIn('id', $used)
            ->oldest('updated_at')
            ->first();
    }
}
