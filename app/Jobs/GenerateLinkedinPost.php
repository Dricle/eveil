<?php

namespace App\Jobs;

use App\Actions\PublishLinkedinPost;
use App\Ai\Agents\LinkedinPostWriter;
use App\Enums\AgentRunStatus;
use App\Enums\AutonomyLevel;
use App\Enums\LinkedinPostSourceType;
use App\Enums\LinkedinPostStatus;
use App\Enums\LinkedinPostVariant;
use App\Enums\OutreachStatus;
use App\Models\AgentRun;
use App\Models\Company;
use App\Models\LinkedinPost;
use App\Models\LinkedinPostExample;
use App\Models\Project;
use App\Notifications\LinkedinPostDrafted;
use App\Services\Linkedin\NewsSearch;
use App\Support\CurrentProject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * One generation cycle: gather every available signal, ask
 * `LinkedinPostWriter` once, persist whatever it decided to write. Mirrors
 * `AnalyzeProject`'s shape.
 */
class GenerateLinkedinPost implements ShouldQueue
{
    use Queueable;

    public function __construct(public Project $project)
    {
        $this->onQueue('ai');
    }

    public function handle(NewsSearch $newsSearch, CurrentProject $currentProject, PublishLinkedinPost $publish): void
    {
        $currentProject->run($this->project, function () use ($newsSearch, $publish): void {
            $clientWon = $this->pendingClientWin();

            $run = AgentRun::create([
                'project_id' => $this->project->id,
                'agent' => LinkedinPostWriter::slug(),
                'status' => AgentRunStatus::Pending,
            ]);

            $agent = new LinkedinPostWriter(
                $this->project,
                $clientWon,
                $newsSearch->recent($this->project),
                $this->recentPublished(),
                $this->rejected(),
                $this->ownWinners(),
                LinkedinPostExample::promptDigest(),
            );

            $response = $agent->recordInto($run)->draft();

            $posts = $this->persist($response->structured, $run->id, $clientWon);

            $this->publishIfAutonomous($posts, $publish);

            // Only what still waits on a person is worth an email: a post
            // that went out on its own needs nobody's review.
            if ($posts->contains(fn (LinkedinPost $post): bool => $post->fresh()?->status === LinkedinPostStatus::Draft)) {
                Notification::send($this->project->notifiableUsers(), LinkedinPostDrafted::for($this->project));
            }
        });
    }

    /**
     * The autonomous LinkedIn setting: publish straight away instead of
     * waiting in the queue. Two cases still stop for a person. Several
     * accounts on the project: which one to post as is a choice nobody has
     * made. A client win: only the anonymized variant ever goes out on its
     * own, since naming a client in public is theirs to agree to, and the
     * named sibling is rejected the same way approving by hand rejects it.
     * A failed publish stays a draft with its error, like the manual path.
     *
     * @param  Collection<int, LinkedinPost>  $posts
     */
    private function publishIfAutonomous(Collection $posts, PublishLinkedinPost $publish): void
    {
        if ($this->project->linkedin_autonomy_level !== AutonomyLevel::Autonomous) {
            return;
        }

        $accounts = $this->project->linkedinAccounts()->get();

        if ($accounts->count() !== 1) {
            return;
        }

        $post = $posts->first(fn (LinkedinPost $post): bool => $post->variant !== LinkedinPostVariant::Named);

        if ($post === null) {
            return;
        }

        $post->update(['linkedin_account_id' => $accounts->sole()->id]);

        $post->sibling()->update([
            'status' => LinkedinPostStatus::Rejected,
            'rejection_reason' => 'Superseded by the other variant.',
        ]);

        $publish->handle($post, $accounts->sole());
    }

    /**
     * The oldest Won company with nobody having posted about it yet -
     * existence-checked against `linkedin_posts` rather than a separate
     * "consumed" column. `Company` and `LinkedinPost` are both scoped to
     * `$this->project` here by `BelongsToProject`, since this runs inside
     * `CurrentProject::run()`.
     */
    private function pendingClientWin(): ?Company
    {
        $alreadyPosted = LinkedinPost::query()
            ->where('source_type', LinkedinPostSourceType::ClientWon)
            ->pluck('source_ref')
            ->filter()
            ->map(fn (string $ref): int => (int) $ref);

        return Company::query()
            ->where('status', OutreachStatus::Won)
            ->whereNotIn('id', $alreadyPosted)
            ->oldest('updated_at')
            ->first();
    }

    /**
     * @return Collection<int, string>
     */
    private function recentPublished(): Collection
    {
        return $this->project->linkedinPosts()
            ->where('status', LinkedinPostStatus::Published)
            ->latest('published_at')
            ->limit(5)
            ->pluck('body');
    }

    /**
     * @return Collection<int, LinkedinPost>
     */
    private function rejected(): Collection
    {
        return $this->project->linkedinPosts()
            ->where('status', LinkedinPostStatus::Rejected)
            ->latest('updated_at')
            ->limit(5)
            ->get(['body', 'rejection_reason']);
    }

    /**
     * @return Collection<int, string>
     */
    private function ownWinners(): Collection
    {
        return $this->project->linkedinPosts()
            ->whereNotNull('promoted_at')
            ->latest('promoted_at')
            ->limit(5)
            ->pluck('body');
    }

    /**
     * @param  array<string, mixed>  $structured
     * @return Collection<int, LinkedinPost> every draft actually written,
     *                                       empty when the writer passed.
     */
    private function persist(array $structured, int $agentRunId, ?Company $clientWon): Collection
    {
        $sourceType = LinkedinPostSourceType::from((string) $structured['source_type']);
        $evidence = (string) $structured['evidence'];

        if ($sourceType === LinkedinPostSourceType::ClientWon && $clientWon !== null) {
            $variants = [
                [LinkedinPostVariant::Named, (string) ($structured['body_named'] ?? '')],
                [LinkedinPostVariant::Anonymized, (string) ($structured['body_anonymized'] ?? '')],
            ];

            $posts = new Collection;

            foreach ($variants as [$variant, $body]) {
                if ($body === '') {
                    continue;
                }

                $posts->push(LinkedinPost::create([
                    'project_id' => $this->project->id,
                    'agent_run_id' => $agentRunId,
                    'source_type' => LinkedinPostSourceType::ClientWon,
                    'source_ref' => (string) $clientWon->id,
                    'variant' => $variant,
                    'evidence' => $evidence,
                    'body' => $body,
                    'status' => LinkedinPostStatus::Draft,
                ]));
            }

            return $posts;
        }

        $body = (string) ($structured['body'] ?? '');

        if ($body === '') {
            return new Collection;
        }

        return new Collection([LinkedinPost::create([
            'project_id' => $this->project->id,
            'agent_run_id' => $agentRunId,
            'source_type' => $sourceType,
            'evidence' => $evidence,
            'body' => $body,
            'status' => LinkedinPostStatus::Draft,
        ])]);
    }
}
