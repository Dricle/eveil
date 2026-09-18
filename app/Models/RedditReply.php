<?php

namespace App\Models;

use App\Enums\RedditReplyAngle;
use App\Enums\RedditReplySource;
use App\Enums\RedditReplyStatus;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\RedditReplyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One drafted or self-reported-posted Reddit reply, project-scoped. No
 * account, no API, no `urn` - Reddit currently blocks new OAuth app
 * registration, so publishing is manual: the user copies the body, posts it
 * on reddit.com themselves, and comes back to `MarkRedditReplyPosted`.
 *
 * `source`/`search_query` record which discovery mechanism found the
 * thread (`App\Services\Reddit\OpportunityScanner` or `SeoThreadFinder`),
 * not which of the up-to-3 drafted angles (`angle`) this row is.
 *
 * `comment_permalink` is self-reported, pasted back by the user - the only
 * way this app ever learns a comment's real Reddit id, which is what makes
 * `FetchRedditReplyStats` able to poll it at all.
 *
 * @property int $id
 * @property int $project_id
 * @property int|null $agent_run_id
 * @property string|null $subreddit
 * @property string $thread_permalink
 * @property string|null $thread_title
 * @property RedditReplySource $source
 * @property string|null $search_query
 * @property RedditReplyAngle $angle
 * @property string $evidence
 * @property string $body
 * @property RedditReplyStatus $status
 * @property string|null $rejection_reason
 * @property Carbon|null $published_at
 * @property string|null $comment_permalink
 * @property int $score
 * @property Carbon|null $stats_checked_at
 * @property Carbon|null $promoted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'project_id', 'agent_run_id', 'subreddit', 'thread_permalink', 'thread_title',
    'source', 'search_query', 'angle', 'evidence', 'body',
    'status', 'rejection_reason', 'published_at', 'comment_permalink',
    'score', 'stats_checked_at', 'promoted_at',
])]
class RedditReply extends Model
{
    /** @use HasFactory<RedditReplyFactory> */
    use BelongsToProject, HasFactory;

    /**
     * @return BelongsTo<AgentRun, $this>
     */
    public function agentRun(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class);
    }

    /**
     * The other up-to-2 angles drafted for this same thread, still a draft.
     * Approving one rejects every sibling: only one angle per thread can
     * ever be published.
     *
     * @return Builder<RedditReply>
     */
    public function sibling(): Builder
    {
        return RedditReply::query()
            ->where('thread_permalink', $this->thread_permalink)
            ->where('angle', '!=', $this->angle)
            ->whereKeyNot($this->id)
            ->where('status', RedditReplyStatus::Draft);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => RedditReplySource::class,
            'angle' => RedditReplyAngle::class,
            'status' => RedditReplyStatus::class,
            'published_at' => 'datetime',
            'stats_checked_at' => 'datetime',
            'promoted_at' => 'datetime',
        ];
    }
}
