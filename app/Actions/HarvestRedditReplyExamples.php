<?php

namespace App\Actions;

use App\Ai\Agents\RedditExampleTriage;
use App\Enums\AgentRunStatus;
use App\Enums\RedditReplyExampleSource;
use App\Models\AgentRun;
use App\Models\Project;
use App\Models\RedditReply;
use App\Models\RedditReplyExample;
use App\Services\Reddit\TopComment;
use App\Support\Settings;
use Illuminate\Support\Collection;

/**
 * Keeps, from the comments a scan already read, the ones another redditor
 * wrote that scored well while recommending a product - the third source of
 * the shared `reddit_reply_examples` bank, alongside a superadmin's own text
 * and `FetchRedditReplyStats` promoting one of our published replies.
 *
 * Fed by `App\Jobs\ScanRedditOpportunities` from the same `TopComments`
 * responses it uses as the writer's tone sample, so harvesting costs no HTTP
 * call of its own and runs one cheap triage per scan cycle rather than one
 * per comment.
 *
 * Two filters before the model sees anything, because the model call is the
 * expensive part: the score floor, and a length floor - a one-line "just use
 * X" is the single most common high-scoring product mention on Reddit and
 * teaches the writer nothing.
 */
class HarvestRedditReplyExamples
{
    /**
     * Short enough to be a bare recommendation rather than an explanation.
     */
    private const MIN_BODY_LENGTH = 120;

    /**
     * One scan cycle can accept many threads, each carrying five comments.
     * The triage prompt stays small enough to run on the cheap model.
     */
    private const BATCH_LIMIT = 20;

    public function __construct(private Settings $settings) {}

    /**
     * @param  Collection<int, TopComment>  $comments  every top comment this scan cycle read, in any order
     * @return int how many joined the bank
     */
    public function handle(Project $project, Collection $comments): int
    {
        if (! $this->settings->bool('reddit_examples.harvest_enabled')) {
            return 0;
        }

        $candidates = $this->candidates($comments);

        if ($candidates->isEmpty()) {
            return 0;
        }

        $run = AgentRun::create([
            'project_id' => $project->id,
            'agent' => RedditExampleTriage::slug(),
            'status' => AgentRunStatus::Pending,
        ]);

        $agent = new RedditExampleTriage($project, $candidates);

        /** @var array<int, array{id?: string, is_good_example?: bool, reason?: string}> $verdicts */
        $verdicts = $agent->recordInto($run)->judge()->structured['items'] ?? [];

        /** @var Collection<string, TopComment> $byId */
        $byId = $candidates->keyBy(fn (TopComment $comment): string => $comment->id);

        $kept = 0;

        foreach ($verdicts as $verdict) {
            if (! ($verdict['is_good_example'] ?? false)) {
                continue;
            }

            $comment = $byId->get(trim((string) ($verdict['id'] ?? '')));

            if ($comment === null) {
                continue;
            }

            // `firstOrCreate` rather than `create`: `candidates()` already
            // rejected everything stored, but two projects scanning the same
            // popular thread in the same minute would otherwise race into the
            // partial unique index.
            RedditReplyExample::query()->firstOrCreate(
                ['reddit_comment_id' => $comment->id],
                [
                    'body' => $comment->body,
                    'source' => RedditReplyExampleSource::Harvested,
                    'comment_permalink' => $comment->permalink !== '' ? $comment->permalink : null,
                    'comment_author' => $comment->author !== '' ? $comment->author : null,
                    'subreddit' => $comment->subreddit,
                    'score' => $comment->score,
                    'harvest_reason' => trim((string) ($verdict['reason'] ?? '')),
                ],
            );

            $kept++;
        }

        return $kept;
    }

    /**
     * @param  Collection<int, TopComment>  $comments
     * @return Collection<int, TopComment>
     */
    private function candidates(Collection $comments): Collection
    {
        $minScore = $this->settings->int('reddit_examples.harvest_min_score');

        $pool = $comments
            ->filter(fn (TopComment $comment): bool => $comment->id !== ''
                && $comment->score >= $minScore
                && mb_strlen($comment->body) >= self::MIN_BODY_LENGTH)
            ->unique(fn (TopComment $comment): string => $comment->id)
            ->values();

        if ($pool->isEmpty()) {
            return $pool;
        }

        $stored = RedditReplyExample::query()
            ->whereIn('reddit_comment_id', $pool->map(fn (TopComment $comment): string => $comment->id)->all())
            ->pluck('reddit_comment_id');

        $ours = $this->publishedByUs($pool);

        return $pool
            ->reject(fn (TopComment $comment): bool => $stored->contains($comment->id) || in_array($comment->id, $ours, true))
            ->take(self::BATCH_LIMIT)
            ->values();
    }

    /**
     * Comment ids, among these, that are one of our own published replies, in
     * any project: those reach the bank through `FetchRedditReplyStats` under
     * their true label, never as a stranger's comment. Matched on the id inside
     * `comment_permalink` rather than the whole link, because that link is
     * pasted back by the user in whatever form their browser gave them.
     *
     * @param  Collection<int, TopComment>  $pool
     * @return array<int, string>
     */
    private function publishedByUs(Collection $pool): array
    {
        // Reddit ids are base 36; anything else would not be safe in the regex.
        $ids = $pool->map(fn (TopComment $comment): string => $comment->id)
            ->filter(fn (string $id): bool => ctype_alnum($id));

        if ($ids->isEmpty()) {
            return [];
        }

        // The comment id is always the link's last segment, the post id never.
        $pattern = '/('.$ids->implode('|').')/?([?#]|$)';

        return RedditReply::query()
            ->withoutGlobalScopes()
            ->where('comment_permalink', '~', $pattern)
            ->pluck('comment_permalink')
            ->map(fn (string $permalink): ?string => preg_match('~'.$pattern.'~', $permalink, $match) === 1 ? $match[1] : null)
            ->filter()
            ->values()
            ->all();
    }
}
