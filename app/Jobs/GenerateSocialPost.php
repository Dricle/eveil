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
use App\Enums\SocialPostVariant;
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
 * One LinkedIn, X or Bluesky post: gather every signal, ask
 * `SocialPostWriter` once, keep what it wrote. One network per job, since
 * each has its own cadence.
 *
 * `$brief` is set when Evie queued it from a conversation ("we just shipped
 * X"), same as `GenerateArticle`: the post is then about that, the other
 * signals are left out, it never publishes on its own, and nobody gets an
 * email, since the user asked for this one and is waiting for it.
 */
class GenerateSocialPost implements ShouldQueue
{
    use Queueable;

    public function __construct(public Project $project, public SocialPlatform $platform, public ?string $brief = null)
    {
        $this->onQueue('ai');
    }

    public function handle(NewsSearch $newsSearch, CurrentProject $currentProject, PublishSocialPost $publish): void
    {
        $currentProject->run($this->project, function () use ($newsSearch, $publish): void {
            $clientWon = $this->brief === null ? $this->pendingClientWin() : null;
            $article = $this->brief === null ? $this->unsharedArticle() : null;

            $run = AgentRun::create([
                'project_id' => $this->project->id,
                'agent' => SocialPostWriter::slug(),
                'status' => AgentRunStatus::Pending,
            ]);

            $structured = (new SocialPostWriter(
                $this->project,
                $this->platform,
                $clientWon,
                $this->brief === null ? $newsSearch->recent($this->project) : new Collection,
                $article,
                $this->posts()->where('status', SocialPostStatus::Published)->latest('published_at')->limit(5)->pluck('body'),
                $this->posts()->where('status', SocialPostStatus::Rejected)->latest('updated_at')->limit(5)->get(['body', 'rejection_reason']),
                $this->posts()->whereNotNull('promoted_at')->latest('promoted_at')->limit(5)->pluck('body'),
                SocialPostExample::promptDigest($this->platform),
                $this->brief,
            ))->recordInto($run)->draft()->structured;

            $posts = $this->persist($structured, $run->id, $clientWon, $article);

            // Asked for from chat: always a draft, since no Evie tool ever
            // publishes, and no email about something the user is waiting for.
            if ($posts->isEmpty() || $this->brief !== null) {
                return;
            }

            $this->publishIfAutonomous($posts, $publish);

            // Only what still waits on a person is worth an email.
            if ($posts->contains(fn (SocialPost $post): bool => $post->fresh()?->status === SocialPostStatus::Draft)) {
                Notification::send($this->project->notifiableUsers(), SocialPostDrafted::for($this->project, $this->platform));
            }
        });
    }

    /**
     * Every draft the writer produced: one post, or a named and an anonymized
     * sibling for a client win.
     *
     * @param  array<string, mixed>  $structured
     * @return Collection<int, SocialPost>
     */
    private function persist(array $structured, int $agentRunId, ?Company $clientWon, ?Article $article): Collection
    {
        // Never trust the model with where a brief came from, or with which
        // row a source points at.
        $sourceType = $this->brief !== null
            ? SocialPostSourceType::Manual
            : SocialPostSourceType::tryFrom((string) ($structured['source_type'] ?? '')) ?? SocialPostSourceType::KnowledgeBase;

        $sourceRef = match ($sourceType) {
            SocialPostSourceType::ClientWon => $clientWon?->id,
            SocialPostSourceType::Article => $article?->id,
            default => null,
        };

        $bodies = $sourceType === SocialPostSourceType::ClientWon && $clientWon !== null
            ? [
                SocialPostVariant::Named->value => $structured['body_named'] ?? '',
                SocialPostVariant::Anonymized->value => $structured['body_anonymized'] ?? '',
            ]
            : ['' => $structured['body'] ?? ''];

        return collect($bodies)
            ->map(fn (mixed $body): string => trim((string) $body))
            ->filter()
            ->map(fn (string $body, string $variant): SocialPost => SocialPost::create([
                'project_id' => $this->project->id,
                'platform' => $this->platform,
                'agent_run_id' => $agentRunId,
                'source_type' => $sourceType,
                'source_ref' => $sourceRef === null ? null : (string) $sourceRef,
                'variant' => SocialPostVariant::tryFrom($variant),
                'evidence' => (string) ($structured['evidence'] ?? ''),
                'body' => $body,
                'status' => SocialPostStatus::Draft,
            ]))
            ->values();
    }

    /**
     * The network's autonomous setting: publish straight away. Two cases still
     * stop for a person. Several accounts on the project: which one to post as
     * is a choice nobody has made. A client win: only the anonymized variant
     * ever goes out on its own, since naming a client in public is theirs to
     * agree to, and the named sibling is rejected the same way approving by
     * hand rejects it. X is always supervised: it is posted by hand.
     *
     * @param  Collection<int, SocialPost>  $posts
     */
    private function publishIfAutonomous(Collection $posts, PublishSocialPost $publish): void
    {
        if ($this->platform->autonomyLevel($this->project) !== AutonomyLevel::Autonomous) {
            return;
        }

        $accounts = $this->project->socialAccounts()
            ->where('platform', $this->platform)
            ->where('status', SocialAccountStatus::Active)
            ->get();

        $post = $posts->first(fn (SocialPost $post): bool => $post->variant !== SocialPostVariant::Named);

        if ($accounts->count() !== 1 || $post === null) {
            return;
        }

        $post->sibling()->update([
            'status' => SocialPostStatus::Rejected,
            'rejection_reason' => 'Superseded by the other variant.',
        ]);

        $publish->handle($post, $accounts->sole());
    }

    /**
     * @return HasMany<SocialPost, Project>
     */
    private function posts(): HasMany
    {
        return $this->project->socialPosts()->where('platform', $this->platform);
    }

    /**
     * The oldest Won company no post on this network is about yet,
     * existence-checked against `social_posts` rather than a separate
     * "consumed" column.
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
