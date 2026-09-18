<?php

namespace App\Jobs;

use App\Ai\Agents\RedditOpportunityTriage;
use App\Ai\Agents\RedditReplyWriter;
use App\Enums\AgentRunStatus;
use App\Enums\RedditReplyAngle;
use App\Enums\RedditReplySource;
use App\Enums\RedditReplyStatus;
use App\Models\AgentRun;
use App\Models\Project;
use App\Models\RedditReply;
use App\Models\RedditReplyExample;
use App\Notifications\RedditRepliesDrafted;
use App\Services\Reddit\OpportunityCandidate;
use App\Services\Reddit\OpportunityScanner;
use App\Services\Reddit\SeoThreadFinder;
use App\Services\Reddit\TopComments;
use App\Support\CurrentProject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * One scan cycle for one project: gather candidates from BOTH discovery
 * mechanisms, triage them in one shared batch, draft up to 3 reply angles
 * for each accepted thread. Mirrors `GenerateLinkedinPost`'s shape.
 */
class ScanRedditOpportunities implements ShouldQueue
{
    use Queueable;

    private const TONE_SAMPLE_LIMIT = 5;

    public function __construct(public Project $project)
    {
        $this->onQueue('ai');
    }

    public function handle(
        OpportunityScanner $scanner,
        SeoThreadFinder $seoThreadFinder,
        TopComments $topComments,
        CurrentProject $currentProject,
    ): void {
        $currentProject->run($this->project, function () use ($scanner, $seoThreadFinder, $topComments): void {
            $candidates = $scanner->find($this->project)
                ->merge($seoThreadFinder->find($this->project))
                ->unique(fn (OpportunityCandidate $candidate): string => $candidate->permalink)
                ->values();

            // An empty scan is a real answer, never a call worth making:
            // nothing to triage, nothing to meter.
            if ($candidates->isEmpty()) {
                return;
            }

            $verdicts = $this->triage($candidates);

            /** @var Collection<string, OpportunityCandidate> $byPermalink */
            $byPermalink = $candidates->keyBy(fn (OpportunityCandidate $candidate): string => $candidate->permalink);

            /** @var Collection<int, array{0: OpportunityCandidate, 1: string}> $accepted */
            $accepted = $verdicts
                ->filter(fn (array $verdict): bool => $verdict['is_opportunity'] ?? false)
                ->map(function (array $verdict) use ($byPermalink): ?array {
                    $candidate = $byPermalink->get(trim((string) ($verdict['permalink'] ?? '')));

                    return $candidate === null ? null : [$candidate, trim((string) ($verdict['reason'] ?? ''))];
                })
                ->filter()
                ->values();

            if ($accepted->isEmpty()) {
                return;
            }

            $ownWinners = $this->project->redditReplies()->whereNotNull('promoted_at')->latest('promoted_at')->limit(5)->pluck('body');
            $sharedPoolDigest = RedditReplyExample::promptDigest();

            $drafted = false;

            foreach ($accepted as [$candidate, $reason]) {
                if ($this->draft($candidate, $reason, $topComments, $ownWinners, $sharedPoolDigest)) {
                    $drafted = true;
                }
            }

            if ($drafted) {
                Notification::send($this->project->users, RedditRepliesDrafted::for($this->project));
            }
        });
    }

    /**
     * @param  Collection<int, OpportunityCandidate>  $candidates
     * @return Collection<int, array{permalink?: string, is_opportunity?: bool, reason?: string}>
     */
    private function triage(Collection $candidates): Collection
    {
        $run = AgentRun::create([
            'project_id' => $this->project->id,
            'agent' => RedditOpportunityTriage::slug(),
            'status' => AgentRunStatus::Pending,
        ]);

        $agent = new RedditOpportunityTriage($this->project, $candidates);

        $response = $agent->recordInto($run)->triage();

        return new Collection($response->structured['items'] ?? []);
    }

    /**
     * @param  Collection<int, string>  $ownWinners
     * @return bool whether at least one angle was actually drafted
     */
    private function draft(OpportunityCandidate $candidate, string $reason, TopComments $topComments, Collection $ownWinners, string $sharedPoolDigest): bool
    {
        $toneSample = $candidate->source === RedditReplySource::SeoThread
            ? implode("\n\n", $candidate->topComments)
            : $topComments->for($candidate->postId, self::TONE_SAMPLE_LIMIT)->implode("\n\n");

        $run = AgentRun::create([
            'project_id' => $this->project->id,
            'agent' => RedditReplyWriter::slug(),
            'status' => AgentRunStatus::Pending,
        ]);

        $agent = new RedditReplyWriter(
            $this->project,
            $candidate->subreddit,
            (string) $candidate->threadTitle,
            $candidate->text,
            $candidate->source,
            $candidate->searchQuery,
            $toneSample,
            $ownWinners,
            $sharedPoolDigest,
        );

        $response = $agent->recordInto($run)->draft();
        $structured = $response->structured;

        $variants = [
            [RedditReplyAngle::ValueComment, (string) ($structured['body_value_comment'] ?? '')],
            [RedditReplyAngle::SoftMention, (string) ($structured['body_soft_mention'] ?? '')],
            [RedditReplyAngle::DmInvite, (string) ($structured['body_dm_invite'] ?? '')],
        ];

        $drafted = false;

        foreach ($variants as [$angle, $body]) {
            if (trim($body) === '') {
                continue;
            }

            RedditReply::create([
                'project_id' => $this->project->id,
                'agent_run_id' => $run->id,
                'subreddit' => $candidate->subreddit,
                'thread_permalink' => $candidate->permalink,
                'thread_title' => $candidate->threadTitle,
                'source' => $candidate->source,
                'search_query' => $candidate->searchQuery,
                'angle' => $angle,
                'evidence' => $reason !== '' ? $reason : 'Matched by the opportunity scan.',
                'body' => $body,
                'status' => RedditReplyStatus::Draft,
            ]);

            $drafted = true;
        }

        return $drafted;
    }
}
