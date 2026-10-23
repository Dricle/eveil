<?php

namespace App\Jobs;

use App\Actions\HarvestRedditReplyExamples;
use App\Ai\Agents\RedditOpportunityTriage;
use App\Ai\Agents\RedditReplyWriter;
use App\Ai\OutOfCredit;
use App\Enums\AgentRunStatus;
use App\Enums\IdeaKind;
use App\Enums\IdeaStatus;
use App\Enums\RedditReplyAngle;
use App\Enums\RedditReplySource;
use App\Enums\RedditReplyStatus;
use App\Models\AgentRun;
use App\Models\Idea;
use App\Models\Project;
use App\Models\RedditReply;
use App\Models\RedditReplyExample;
use App\Notifications\RedditRepliesDrafted;
use App\Services\Reddit\OpportunityCandidate;
use App\Services\Reddit\OpportunityScanner;
use App\Services\Reddit\SeoThreadFinder;
use App\Services\Reddit\TopComment;
use App\Services\Reddit\TopComments;
use App\Support\CurrentProject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * One scan cycle for one project: gather candidates from BOTH discovery
 * mechanisms, triage them in one shared batch, draft up to 3 reply angles
 * for each accepted thread. Mirrors `GenerateSocialPost`'s shape.
 *
 * The top comments read to give the writer a tone sample are also handed,
 * once per cycle, to `HarvestRedditReplyExamples` - the only place in the
 * app where a comment is seen with a real score on it, so the harvest reads
 * what is already here rather than fetching the same threads again.
 */
class ScanRedditOpportunities implements ShouldQueue
{
    use Queueable;

    private const TONE_SAMPLE_LIMIT = 5;

    /**
     * ~1500 chars per candidate (3000 for SEO threads) keeps a batch of 40
     * well under 50k tokens, a quarter of the 200k context window.
     */
    private const TRIAGE_BATCH_SIZE = 40;

    public function __construct(public Project $project)
    {
        $this->onQueue('ai');
    }

    public function handle(
        OpportunityScanner $scanner,
        SeoThreadFinder $seoThreadFinder,
        TopComments $topComments,
        HarvestRedditReplyExamples $harvester,
        CurrentProject $currentProject,
    ): void {
        $currentProject->run($this->project, function () use ($scanner, $seoThreadFinder, $topComments, $harvester): void {
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

            $this->noteArticleIdeas($verdicts, $byPermalink);

            /** @var Collection<int, array{0: OpportunityCandidate, 1: string}> $accepted */
            $accepted = $verdicts
                ->filter(fn (array $verdict): bool => $verdict['is_opportunity'] ?? false)
                ->map(function (array $verdict) use ($byPermalink): ?array {
                    $candidate = $byPermalink->get(trim((string) ($verdict['permalink'] ?? '')));

                    return $candidate === null ? null : [$candidate, trim((string) ($verdict['reason'] ?? ''))];
                })
                ->filter()
                ->values();

            // A `seo_thread` candidate carries the comments `SeoThreadFinder`
            // already fetched to judge it, accepted or not: a thread rejected
            // because its answers already cover the question well is exactly
            // where the best examples sit. The harvest never fetches its own.
            /** @var Collection<int, TopComment> $read */
            $read = $candidates
                ->filter(fn (OpportunityCandidate $candidate): bool => $candidate->source === RedditReplySource::SeoThread)
                ->flatMap(fn (OpportunityCandidate $candidate): array => $candidate->topComments)
                ->values();

            if ($accepted->isEmpty()) {
                $this->harvest($harvester, $read);

                return;
            }

            $ownWinners = $this->project->redditReplies()->whereNotNull('promoted_at')->latest('promoted_at')->limit(5)->pluck('body');
            $sharedPoolDigest = RedditReplyExample::promptDigest();

            $drafted = false;

            foreach ($accepted as [$candidate, $reason]) {
                // A `subreddit_scan` candidate needs the single call that has
                // ever been made here; its comments join what the harvest sees.
                if ($candidate->source === RedditReplySource::SeoThread) {
                    $comments = new Collection($candidate->topComments);
                } else {
                    $comments = $topComments->for($candidate->postId, self::TONE_SAMPLE_LIMIT);
                    $read = $read->merge($comments);
                }

                if ($this->draft($candidate, $reason, $comments, $ownWinners, $sharedPoolDigest)) {
                    $drafted = true;
                }
            }

            if ($drafted) {
                Notification::send($this->project->notifiableUsers(), RedditRepliesDrafted::for($this->project));
            }

            $this->harvest($harvester, $read);
        });
    }

    /**
     * The harvest is a side benefit of the scan, never a reason for it to
     * fail: drafts are already saved and announced by the time it runs, and a
     * retry would skip those threads anyway. Its failure is reported, the
     * scan carries on.
     *
     * @param  Collection<int, TopComment>  $read
     */
    private function harvest(HarvestRedditReplyExamples $harvester, Collection $read): void
    {
        try {
            $harvester->handle($this->project, $read);
        } catch (OutOfCredit) {
            // Not a failure: the spend guard already told the owners.
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Threads the triage judged worth an article, reply opportunity or not.
     * A thread already noted (open, used or dismissed) is left alone: the
     * user's "no" sticks, and one thread is one idea.
     *
     * @param  Collection<int, array{permalink?: string, is_opportunity?: bool, reason?: string, article_angle?: string}>  $verdicts
     * @param  Collection<string, OpportunityCandidate>  $byPermalink
     */
    private function noteArticleIdeas(Collection $verdicts, Collection $byPermalink): void
    {
        foreach ($verdicts as $verdict) {
            $angle = trim((string) ($verdict['article_angle'] ?? ''));
            $candidate = $byPermalink->get(trim((string) ($verdict['permalink'] ?? '')));

            if ($angle === '' || $candidate === null) {
                continue;
            }

            Idea::query()->firstOrCreate(
                ['project_id' => $this->project->id, 'kind' => IdeaKind::Article, 'source_ref' => $candidate->permalink],
                [
                    'source' => 'reddit',
                    'title' => $candidate->threadTitle ?? Str::limit($candidate->text, 120),
                    'angle' => $angle,
                    'status' => IdeaStatus::Open,
                ],
            );
        }
    }

    /**
     * @param  Collection<int, OpportunityCandidate>  $candidates
     * @return Collection<int, array{permalink?: string, is_opportunity?: bool, reason?: string, article_angle?: string}>
     */
    private function triage(Collection $candidates): Collection
    {
        // One call per batch: 50 candidates per subreddit across every
        // subreddit blew past the model's context window in a single prompt.
        return $candidates->chunk(self::TRIAGE_BATCH_SIZE)->flatMap(function (Collection $batch): array {
            $run = AgentRun::create([
                'project_id' => $this->project->id,
                'agent' => RedditOpportunityTriage::slug(),
                'status' => AgentRunStatus::Pending,
            ]);

            $agent = new RedditOpportunityTriage($this->project, $batch->values());

            return $agent->recordInto($run)->triage()->structured['items'] ?? [];
        })->values();
    }

    /**
     * @param  Collection<int, TopComment>  $comments  this thread's own top comments, the register to match
     * @param  Collection<int, string>  $ownWinners
     * @return bool whether at least one angle was actually drafted
     */
    private function draft(OpportunityCandidate $candidate, string $reason, Collection $comments, Collection $ownWinners, string $sharedPoolDigest): bool
    {
        $toneSample = $comments->map(fn (TopComment $comment): string => $comment->body)->implode("\n\n");

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
            [RedditReplyAngle::DirectMention, (string) ($structured['body_direct_mention'] ?? '')],
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
