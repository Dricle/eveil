<?php

namespace App\Jobs;

use App\Ai\Agents\ArticleWriter;
use App\Enums\AgentRunStatus;
use App\Enums\AnalysisType;
use App\Enums\ArticleSourceType;
use App\Enums\ArticleStatus;
use App\Enums\IdeaKind;
use App\Enums\IdeaStatus;
use App\Enums\OutreachStatus;
use App\Models\AgentRun;
use App\Models\Article;
use App\Models\Company;
use App\Models\Idea;
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
 * once, keep the draft. Same shape as `GenerateLinkedinPost`.
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
            $idea = $this->ideaId === null ? null : Idea::query()->where('status', IdeaStatus::Open)->find($this->ideaId);

            if ($this->ideaId !== null && $idea === null) {
                return;
            }

            $brief = $idea === null
                ? $this->brief
                : "Write the article this Reddit discussion suggests.\nThread: {$idea->title} ({$idea->source_ref})\nAngle: {$idea->angle}";

            $clientWon = $this->pendingClientWin();

            $run = AgentRun::create([
                'project_id' => $this->project->id,
                'agent' => ArticleWriter::slug(),
                'status' => AgentRunStatus::Pending,
            ]);

            $agent = new ArticleWriter(
                $this->project,
                $this->sitePages(),
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
                Idea::query()
                    ->where('kind', IdeaKind::Article)
                    ->where('source_ref', $sourceRef)
                    ->where('status', IdeaStatus::Open)
                    ->update(['status' => IdeaStatus::Used, 'article_id' => $article->id]);
            }

            // Only the cadence emails: a brief or a clicked idea means the
            // user asked for this one and is waiting for it.
            if ($brief === null) {
                Notification::send($this->project->notifiableUsers(), ArticleDrafted::for($this->project));
            }
        });
    }

    /**
     * What the site already covers, from the latest website reading: the
     * writer must not draft an article the site already has.
     *
     * @return Collection<int, array{url: string, title: string}>
     */
    private function sitePages(): Collection
    {
        $raw = $this->project->analyses()
            ->where('type', AnalysisType::Website)
            ->latest('id')
            ->value('raw');

        return collect(is_array($raw['pages'] ?? null) ? $raw['pages'] : [])
            ->filter(fn (mixed $page): bool => is_array($page) && isset($page['url']))
            ->map(fn (array $page): array => ['url' => (string) $page['url'], 'title' => (string) ($page['title'] ?? '')])
            ->values();
    }

    /**
     * Discussions the Reddit scan noted as worth an article and nobody has
     * used or dismissed yet.
     *
     * @return Collection<int, array{title: string, permalink: string, angle: string}>
     */
    private function openIdeas(): Collection
    {
        return Idea::query()
            ->where('kind', IdeaKind::Article)
            ->where('status', IdeaStatus::Open)
            ->latest('id')
            ->limit(self::IDEA_LIMIT)
            ->get()
            ->map(fn (Idea $idea): array => [
                'title' => $idea->title,
                'permalink' => $idea->source_ref,
                'angle' => $idea->angle,
            ]);
    }

    /**
     * The oldest Won company no article is about yet, same rule as
     * `GenerateLinkedinPost::pendingClientWin()`.
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
