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
 * every project. Copy of `LinkedinPostExample` - shared instance-wide on
 * purpose, fed by exactly two trusted sources: a superadmin typing one in,
 * or `FetchRedditReplyStats` crossing a real, externally-measured score.
 *
 * @property int $id
 * @property string $body
 * @property RedditReplyExampleSource $source
 * @property int|null $reddit_reply_id
 * @property int|null $added_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['body', 'source', 'reddit_reply_id', 'added_by_user_id'])]
class RedditReplyExample extends Model
{
    /** @use HasFactory<RedditReplyExampleFactory> */
    use HasFactory;

    public const SAMPLE_SIZE = 10;

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
     * the first example - manual or promoted - actually exists.
     */
    public static function promptDigest(int $limit = self::SAMPLE_SIZE): string
    {
        $examples = static::query()->inRandomOrder()->limit($limit)->pluck('body');

        if ($examples->isEmpty()) {
            return '';
        }

        return "## Proven Reddit replies from across Eveil\n\n".$examples->implode("\n\n---\n\n");
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
