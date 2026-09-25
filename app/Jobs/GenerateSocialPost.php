<?php

namespace App\Jobs;

use App\Actions\PublishSocialPost;
use App\Ai\Agents\SocialPostWriter;
use App\Enums\AgentRunStatus;
use App\Enums\ArticleStatus;
use App\Enums\AutonomyLevel;
use App\Enums\OutreachStatus;
use App\Enums\SocialAccountStatus;
use App\Enums\SocialPlatform;
use App\Enums\SocialPostSourceType;
use App\Enums\SocialPostStatus;
use App\Models\AgentRun;
use App\Models\Article;
use App\Models\Company;
use App\Models\Project;
use App\Models\SocialPost;
use App\Models\SocialPostExample;
use App\Notifications\SocialPostDrafted;
use App\Services\Linkedin\NewsSearch;
use App\Support\CurrentProject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * One X or Bluesky post: gather every signal, ask `SocialPostWriter` once,
 * keep the draft. Same shape as `GenerateLinkedinPost`, one network per job
 * since each has its own cadence.
 */
class GenerateSocialPost implements ShouldQueue
{
    use Queueable;

    public function __construct(public Project $project, public SocialPlatform $platform)
    {
        $this->onQueue('ai');
    }

    public function handle(NewsSearch $newsSearch, CurrentProject $currentProject, PublishSocialPost $publish): void
    {
        $currentProject->run($this->project, function () use ($newsSearch, $publish): void {
            $clientWon = $this->pendingClientWin();
            $article = $this->unsharedArticle();

            $run = AgentRun::create([
                'project_id' => $this->project->id,
                'agent' => SocialPostWriter::slug(),
                'status' => AgentRunStatus::Pending,
            ]);

            $structured = (new SocialPostWriter(
                $this->project,
                $this->platform,
                $clientWon,
                $newsSearch->recent($this->project),
                $article,
                $this->posts()->where('status', SocialPostStatus::Published)->latest('published_at')->limit(5)->pluck('body'),
                $this->posts()->where('status', SocialPostStatus::Rejected)->latest('updated_at')->limit(5)->get(['body', 'rejection_reason']),
                $this->posts()->whereNotNull('promoted_at')->latest('promoted_at')->limit(5)->pluck('body'),
                SocialPostExample::promptDigest($this->platform),
            ))->recordInto($run)->draft()->structured;

            $body = trim((string) ($structured['body'] ?? ''));

            if ($body === '') {
                return;
            }

            $sourceType = SocialPostSourceType::tryFrom((string) ($structured['source_type'] ?? '')) ?? SocialPostSourceType::KnowledgeBase;

            // Never trust the model with which row a source points at.
            $sourceRef = match ($sourceType) {
                SocialPostSourceType::ClientWon => $clientWon?->id,
                SocialPostSourceType::Article => $article?->id,
                default => null,
            };

            $post = SocialPost::create([
                'project_id' => $this->project->id,
                'platform' => $this->platform,
                'agent_run_id' => $run->id,
                'source_type' => $sourceType,
                'source_ref' => $sourceRef === null ? null : (string) $sourceRef,
                'evidence' => (string) ($structured['evidence'] ?? ''),
                'body' => $body,
                'status' => SocialPostStatus::Draft,
            ]);

            $this->publishIfAutonomous($post, $publish);

            // Only what still waits on a person is worth an email.
            if ($post->fresh()?->status === SocialPostStatus::Draft) {
                Notification::send($this->project->notifiableUsers(), SocialPostDrafted::for($this->project, $this->platform));
            }
        });
    }

    /**
     * The network's autonomous setting: publish straight away. Several
     * accounts on the project still stop for a person, since which one to
     * post as is a choice nobody has made. X is always supervised: it is
     * posted by hand.
     */
    private function publishIfAutonomous(SocialPost $post, PublishSocialPost $publish): void
    {
        if ($this->platform->autonomyLevel($this->project) !== AutonomyLevel::Autonomous) {
            return;
        }

        $accounts = $this->project->socialAccounts()
            ->where('platform', $this->platform)
            ->where('status', SocialAccountStatus::Active)
            ->get();

        if ($accounts->count() === 1) {
            $publish->handle($post, $accounts->sole());
        }
    }

    /**
     * @return HasMany<SocialPost, Project>
     */
    private function posts(): HasMany
    {
        return $this->project->socialPosts()->where('platform', $this->platform);
    }

    /**
     * The oldest Won company no post on this network is about yet, same rule
     * as `GenerateLinkedinPost::pendingClientWin()`.
     */
    private function pendingClientWin(): ?Company
    {
        return Company::query()
            ->where('status', OutreachStatus::Won)
            ->whereNotIn('id', $this->usedRefs(SocialPostSourceType::ClientWon))
            ->oldest('updated_at')
            ->first();
    }

    /**
     * The latest article published in the last month and not shared on this
     * network yet: the project's own content is the easiest post there is.
     */
    private function unsharedArticle(): ?Article
    {
        return Article::query()
            ->where('status', ArticleStatus::Published)
            ->whereNotNull('published_url')
            ->where('published_at', '>=', now()->subDays(30))
            ->whereNotIn('id', $this->usedRefs(SocialPostSourceType::Article))
            ->latest('published_at')
            ->first();
    }

    /**
     * @return Collection<int, int>
     */
    private function usedRefs(SocialPostSourceType $sourceType): Collection
    {
        return $this->posts()
            ->where('source_type', $sourceType)
            ->pluck('source_ref')
            ->filter()
            ->map(fn (string $ref): int => (int) $ref);
    }
}
