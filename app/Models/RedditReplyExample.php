<?php

namespace App\Models;

use App\Enums\RedditReplyExampleSource;
use Database\Factories\RedditReplyExampleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A Reddit reply worth learning from, fed back into `RedditReplyWriter` for
 * every project. Copy of `SocialPostExample` - shared instance-wide on
 * purpose, fed by three sources: a superadmin typing one in,
 * `FetchRedditReplyStats` crossing a real, externally-measured score on one
 * of our own published replies, and `HarvestRedditReplyExamples` keeping a
 * stranger's high-scoring product recommendation off a thread a scan read.
 *
 * The third is the one that is not ours, so the columns describing where it
 * came from are set on it alone, and `promptDigest()` keeps it in its own
 * labelled section.
 *
 * @property int $id
 * @property string $body
 * @property RedditReplyExampleSource $source
 * @property int|null $reddit_reply_id
 * @property int|null $added_by_user_id
 * @property string|null $reddit_comment_id
 * @property string|null $comment_permalink
 * @property string|null $comment_author
 * @property string|null $subreddit
 * @property int|null $score
 * @property string|null $harvest_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'body',
    'source',
    'reddit_reply_id',
    'added_by_user_id',
    'reddit_comment_id',
    'comment_permalink',
    'comment_author',
    'subreddit',
    'score',
    'harvest_reason',
])]
class RedditReplyExample extends Model
{
    /** @use HasFactory<RedditReplyExampleFactory> */
    use HasFactory;

    public const SAMPLE_SIZE = 10;

    /**
     * Deliberately smaller than `SAMPLE_SIZE`: harvested examples are other
     * people's writing, so they are there to show how a product mention lands
     * in public, never to become the bulk of what the writer imitates.
     */
    public const HARVESTED_SAMPLE_SIZE = 5;

    /**
     * @return BelongsTo<RedditReply, $this>
     */
    public function redditReply(): BelongsTo
    {
        return $this->belongsTo(RedditReply::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }

    /**
     * A random few-shot section for the writer's prompt. Empty on a fresh
     * install with nothing in the bank yet, so behaviour is unchanged until
     * the first example actually exists.
     *
     * Two sections, never one list: ours and other people's are different
     * material. A harvested comment is a public comment under somebody's real
     * name, and a writer told it is "a proven reply from across Eveil" has no
     * reason not to lift a sentence out of it and post that sentence back
     * into the same subreddit.
     */
    public static function promptDigest(int $limit = self::SAMPLE_SIZE): string
    {
        $sections = [];

        $ours = static::query()
            ->whereIn('source', [RedditReplyExampleSource::Manual, RedditReplyExampleSource::Promoted])
            ->inRandomOrder()
            ->limit($limit)
            ->pluck('body');

        if ($ours->isNotEmpty()) {
            $sections[] = "## Proven Reddit replies from across Eveil\n\n".$ours->implode("\n\n---\n\n");
        }

        $harvested = static::query()
            ->where('source', RedditReplyExampleSource::Harvested)
            ->inRandomOrder()
            ->limit(self::HARVESTED_SAMPLE_SIZE)
            ->pluck('body');

        if ($harvested->isNotEmpty()) {
            $sections[] = "## High-scoring product mentions written by other redditors\n\n"
                ."These are not ours. Each is a comment somebody else posted publicly under "
                ."their own name, kept because it recommended a product and the thread upvoted "
                ."it. Read them for what earns a product mention its upvotes rather than its "
                ."downvotes. Never reuse a sentence, a phrase or a turn of expression from "
                ."them: the reply you are writing may well be posted in the same subreddit.\n\n"
                .$harvested->implode("\n\n---\n\n");
        }

        return implode("\n\n", $sections);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => RedditReplyExampleSource::class,
        ];
    }
}
