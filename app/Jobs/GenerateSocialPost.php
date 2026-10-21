<?php

namespace App\Jobs;

use App\Actions\AddSocialPostImage;
use App\Actions\PublishSocialPost;
use App\Ai\Agents\SocialPostWriter;
use App\Enums\AgentRunStatus;
use App\Enums\ArticleStatus;
use App\Enums\AutonomyLevel;
use App\Enums\BrandAssetKind;
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
use App\Support\SocialPostProgress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * One LinkedIn, X, Bluesky or Instagram post: gather every signal, ask
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

    public function handle(NewsSearch $newsSearch, CurrentProject $currentProject, PublishSocialPost $publish, SocialPostProgress $progress): void
    {
        $progress->startWriting($this->project, $this->platform);

        try {
            $this->write($newsSearch, $currentProject, $publish);
        } finally {
            $progress->finishWriting($this->project, $this->platform);
        }
    }

    private function write(NewsSearch $newsSearch, CurrentProject $currentProject, PublishSocialPost $publish): void
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
                $this->illustrations(),
            ))->recordInto($run)->draft()->structured;

            $posts = $this->persist($structured, $run->id, $clientWon, $article);

            $this->attachImage($posts, $structured);

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
            // A brief about a client win still gets the two-variant shape back
            // with `body` empty: take the anonymized one rather than drop it.
            : ['' => ($structured['body'] ?? '') ?: ($structured['body_anonymized'] ?? '') ?: ($structured['body_named'] ?? '')];

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
     * The picture a network like Instagram cannot post without, drawn once
     * and shared by a client win's two siblings: they tell the same story.
     * A failed drawing keeps the drafts, with the reason on them, and
     * approving one draws again (`PublishSocialPost`). The brief is kept on
     * each draft either way: every image asked for later starts from it.
     *
     * @param  Collection<int, SocialPost>  $posts
     * @param  array<string, mixed>  $structured
     */
    private function attachImage(Collection $posts, array $structured): void
    {
        if (! $this->platform->requiresImage() || $posts->isEmpty()) {
            return;
        }

        $brief = trim((string) ($structured['image_brief'] ?? '')) ?: $posts->first()->body;

        // Never trust the model with which rows exist: only this project's
        // own illustrations, the ones it was shown.
        $referenceIds = array_values(array_intersect(
            array_map('intval', (array) ($structured['illustration_ids'] ?? [])),
            array_keys($this->illustrations()),
        ));

        $posts->each->update(['image_brief' => $brief, 'image_reference_ids' => $referenceIds]);

        // The drafts are already on the page: say their image is coming.
        $progress = app(SocialPostProgress::class);
        $posts->each(fn (SocialPost $post) => $progress->startDrawing($post));

        try {
            $jpeg = app(AddSocialPostImage::class)->draw($this->project, $this->platform, $brief, illustrationIds: $referenceIds);
        } catch (Throwable $e) {
            $posts->each->update(['last_error' => "The image could not be generated: {$e->getMessage()}"]);

            return;
        } finally {
            $posts->each(fn (SocialPost $post) => $progress->finishDrawing($post));
        }

        // A file of its own per sibling, so deleting one never takes the
        // other's image with it. Only the ones still drafts: one may have
        // been rejected or deleted while the image was being drawn.
        foreach ($posts->map->fresh()->filter(fn (?SocialPost $post): bool => $post?->status === SocialPostStatus::Draft) as $post) {
            $image = $post->addMediaFromString($jpeg)
                ->usingFileName(Str::uuid().'.jpg')
                ->withCustomProperties(['brief' => $brief])
                ->toMediaCollection('images');

            $post->update(['image_media_id' => $image->id]);
        }
    }

    /**
     * The project's official illustrations the writer may pick for the
     * image, media id => what each shows. None for a network posting text.
     *
     * @return array<int, string>
     */
    private function illustrations(): array
    {
        if (! $this->platform->requiresImage()) {
            return [];
        }

        return $this->project->brandAssets(BrandAssetKind::Illustration)
            ->mapWithKeys(fn (Media $media): array => [$media->id => (string) ($media->getCustomProperty('description') ?: $media->name)])
            ->all();
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

        // Read again: the drafts were on the page while their image was drawn,
        // and the user may have rejected or edited one in the meantime.
        $post = $posts->first(fn (SocialPost $post): bool => $post->variant !== SocialPostVariant::Named)?->fresh();

        // No image, no Instagram post: it stays a draft showing why.
        if ($accounts->count() !== 1 || $post === null || $post->status !== SocialPostStatus::Draft || ($this->platform->requiresImage() && $post->image_media_id === null)) {
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
