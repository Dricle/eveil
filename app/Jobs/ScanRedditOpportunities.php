<?php

namespace App\Jobs;

use App\Actions\HarvestRedditReplyExamples;
use App\Actions\NoteArticleIdeas;
use App\Ai\Agents\RedditOpportunityTriage;
use App\Ai\Agents\RedditReplyWriter;
use App\Ai\OutOfCredit;
use App\Enums\AgentRunStatus;
use App\Enums\CompetitorSignal;
use App\Enums\InsightKind;
use App\Enums\InsightStatus;
use App\Enums\RedditReplyAngle;
use App\Enums\RedditReplySource;
use App\Enums\RedditReplyStatus;
use App\Models\AgentRun;
use App\Models\Insight;
use App\Models\Project;
use App\Models\RedditReply;
use App\Models\RedditReplyExample;
use App\Models\RedditTriagedThread;
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
     * ~1500 chars per candidate (2500 for a comment with its post, 3000 for
     * SEO threads) keeps a batch of 40 well under 50k tokens, a quarter of
     * the 200k context window.
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
        NoteArticleIdeas $noteArticleIdeas,
        CurrentProject $currentProject,
    ): void {
        $currentProject->run($this->project, function () use ($scanner, $seoThreadFinder, $topComments, $harvester, $noteArticleIdeas): void {
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

            $this->noteArticleIdeas($noteArticleIdeas, $verdicts, $byPermalink);
            $this->noteCompetitorMentions($verdicts, $byPermalink);
            $this->noteFeatureIdeas($verdicts, $byPermalink);
            $this->rememberRejected($verdicts, $byPermalink);

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
     * Every candidate the triage turned down, so the next scan skips it
     * instead of paying to reject it again. Accepted ones are left out: they
     * get a reply row once drafted, and one whose drafting failed must come
     * back next scan. Expired rejections are pruned here, on the only write.
     *
     * @param  Collection<int, array{permalink?: string, is_opportunity?: bool, reason?: string, article_angle?: string, competitor?: string, competitor_signal?: string, competitor_summary?: string, feature_idea?: string, feature_evidence?: string}>  $verdicts
     * @param  Collection<string, OpportunityCandidate>  $byPermalink
     */
    private function rememberRejected(Collection $verdicts, Collection $byPermalink): void
    {
        $now = now();

        $rows = $verdicts
            ->reject(fn (array $verdict): bool => $verdict['is_opportunity'] ?? false)
            ->map(fn (array $verdict): string => trim((string) ($verdict['permalink'] ?? '')))
            ->filter(fn (string $permalink): bool => $byPermalink->has($permalink))
            ->unique()
            ->map(fn (string $permalink): array => ['project_id' => $this->project->id, 'permalink' => $permalink, 'triaged_at' => $now])
            ->values()
            ->all();

        if ($rows !== []) {
            RedditTriagedThread::query()->upsert($rows, ['project_id', 'permalink'], ['triaged_at']);
        }

        RedditTriagedThread::query()->where('triaged_at', '<', $now->copy()->subDays(RedditTriagedThread::TTL_DAYS))->delete();
    }

    /**
     * Threads the triage judged worth an article, reply opportunity or not,
     * handed to `NoteArticleIdeas` to group into topics.
     *
     * @param  Collection<int, array{permalink?: string, is_opportunity?: bool, reason?: string, article_angle?: string, competitor?: string, competitor_signal?: string, competitor_summary?: string, feature_idea?: string, feature_evidence?: string}>  $verdicts
     * @param  Collection<string, OpportunityCandidate>  $byPermalink
     */
    private function noteArticleIdeas(NoteArticleIdeas $noteArticleIdeas, Collection $verdicts, Collection $byPermalink): void
    {
        $threads = $verdicts
            ->map(function (array $verdict) use ($byPermalink): ?array {
                $angle = trim((string) ($verdict['article_angle'] ?? ''));
                $candidate = $byPermalink->get(trim((string) ($verdict['permalink'] ?? '')));

                return $angle === '' || $candidate === null ? null : [
                    'angle' => $angle,
                    'title' => $candidate->threadTitle ?? Str::limit($candidate->text, 120),
                    'url' => $candidate->permalink,
                    'subreddit' => $candidate->subreddit,
                ];
            })
            ->filter()
            ->values();

        // A side benefit of the scan, like the harvest: never a reason to
        // lose the reply drafts still to come.
        try {
            $noteArticleIdeas->handle($this->project, $threads);
        } catch (OutOfCredit) {
            // Not a failure: the spend guard already told the owners.
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Capabilities people on a thread ask for and this product lacks, noted
     * as `feature` recommendations next to the competitor gaps: same list,
     * same done/dismissed lifecycle, and a dismissed one never comes back.
     * One thread is one idea, the first reading wins. No impact or effort
     * guess: one thread says a need exists, not how big it is.
     *
     * @param  Collection<int, array{permalink?: string, is_opportunity?: bool, reason?: string, article_angle?: string, competitor?: string, competitor_signal?: string, competitor_summary?: string, feature_idea?: string, feature_evidence?: string}>  $verdicts
     * @param  Collection<string, OpportunityCandidate>  $byPermalink
     */
    private function noteFeatureIdeas(Collection $verdicts, Collection $byPermalink): void
    {
        foreach ($verdicts as $verdict) {
            $idea = trim((string) ($verdict['feature_idea'] ?? ''));
            $evidence = trim((string) ($verdict['feature_evidence'] ?? ''));
            $candidate = $byPermalink->get(trim((string) ($verdict['permalink'] ?? '')));

            if ($idea === '' || $evidence === '' || $candidate === null) {
                continue;
            }

            Insight::query()->firstOrCreate(
                ['project_id' => $this->project->id, 'kind' => InsightKind::Feature, 'source_ref' => $candidate->permalink],
                [
                    'source' => 'reddit',
                    'title' => $idea,
                    'angle' => $evidence,
                    'url' => $candidate->permalink,
                    'details' => ['subreddit' => $candidate->subreddit, 'thread_title' => $candidate->threadTitle],
                    'status' => InsightStatus::Open,
                ],
            );
        }
    }

    /**
     * Threads the triage read as an opinion on a named competitor, for the
     * Insights feed and the feature-gap pass. Only a competitor the knowledge
     * base actually names is kept, under its knowledge-base spelling, so the
     * feed's competitor filter never grows a name nobody chose. One thread is
     * one mention, the first reading wins.
     *
     * @param  Collection<int, array{permalink?: string, is_opportunity?: bool, reason?: string, article_angle?: string, competitor?: string, competitor_signal?: string, competitor_summary?: string, feature_idea?: string, feature_evidence?: string}>  $verdicts
     * @param  Collection<string, OpportunityCandidate>  $byPermalink
     */
    private function noteCompetitorMentions(Collection $verdicts, Collection $byPermalink): void
    {
        $competitors = collect(is_array($this->project->knowledge_base['competitors'] ?? null) ? $this->project->knowledge_base['competitors'] : [])
            ->filter(fn (mixed $name): bool => is_string($name) && trim($name) !== '')
            ->mapWithKeys(fn (string $name): array => [mb_strtolower(trim($name)) => trim($name)]);

        foreach ($verdicts as $verdict) {
            $competitor = $competitors->get(mb_strtolower(trim((string) ($verdict['competitor'] ?? ''))));
            $signal = CompetitorSignal::tryFrom((string) ($verdict['competitor_signal'] ?? ''));
            $summary = trim((string) ($verdict['competitor_summary'] ?? ''));
            $candidate = $byPermalink->get(trim((string) ($verdict['permalink'] ?? '')));

            if ($competitor === null || $signal === null || $summary === '' || $candidate === null) {
                continue;
            }

            Insight::query()->firstOrCreate(
                ['project_id' => $this->project->id, 'kind' => InsightKind::CompetitorMention, 'source_ref' => $candidate->permalink],
                [
                    'source' => 'reddit',
                    'title' => $candidate->threadTitle ?? Str::limit($candidate->text, 120),
                    'angle' => $summary,
                    'url' => $candidate->permalink,
                    'details' => ['competitor' => $competitor, 'signal' => $signal->value, 'subreddit' => $candidate->subreddit],
                    'status' => InsightStatus::Open,
                ],
            );
        }
    }

    /**
     * @param  Collection<int, OpportunityCandidate>  $candidates
     * @return Collection<int, array{permalink?: string, is_opportunity?: bool, reason?: string, article_angle?: string, competitor?: string, competitor_signal?: string, competitor_summary?: string, feature_idea?: string, feature_evidence?: string}>
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
            $candidate->parentPost,
            $candidate->author,
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
