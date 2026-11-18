<?php

namespace App\Models;

use App\Enums\InsightKind;
use App\Enums\InsightStatus;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\InsightFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One item of the Insights feed: something Eveil noticed while reading. A
 * Reddit discussion worth an article, a Reddit thread naming a competitor,
 * an acquisition lever missing from the site, or a feature competitors sell
 * and this product lacks. `read_at` is the feed's unread marker; `status`
 * says whether it was acted on (`used`) or turned down (`dismissed`).
 *
 * An article idea is a topic, not a thread: every Reddit thread asking the
 * same question lands on one row (`App\Actions\NoteArticleIdeas`), listed in
 * `details.threads` and counted in `mentions`. They are a backlog the article
 * cadence works through, so the feed only shows one once it keeps coming up.
 *
 * @property int $id
 * @property int $project_id
 * @property InsightKind $kind
 * @property string $source
 * @property string $source_ref
 * @property string $title
 * @property string $angle
 * @property string|null $url
 * @property array<string, mixed>|null $details
 * @property int $mentions
 * @property InsightStatus $status
 * @property int|null $article_id
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['project_id', 'kind', 'source', 'source_ref', 'title', 'angle', 'status', 'article_id', 'url', 'details', 'mentions', 'read_at'])]
class Insight extends Model
{
    /** @use HasFactory<InsightFactory> */
    use BelongsToProject, HasFactory;

    /**
     * How many threads must ask for an article before the topic is worth
     * interrupting the user in the feed. Below it, it waits on the SEO page.
     */
    public const FEED_MENTIONS = 5;

    /**
     * A topic still asked by a single thread after this long was a one-off:
     * it is forgotten, and comes back if people start asking again.
     */
    public const LONE_TOPIC_DAYS = 30;

    /**
     * What the Insights feed lists: everything but the article topics too
     * quiet to be worth a look yet.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function inFeed(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('kind', '!=', InsightKind::Article)
            ->orWhere('mentions', '>=', self::FEED_MENTIONS));
    }

    /**
     * An article topic's threads; an idea noted before topics existed is its
     * own single thread.
     *
     * @return list<array{title: string, url: string, subreddit: string|null}>
     */
    public function threads(): array
    {
        $threads = is_array($this->details['threads'] ?? null)
            ? $this->details['threads']
            : [['title' => $this->title, 'url' => $this->source_ref]];

        $normalised = [];

        foreach ($threads as $thread) {
            if (is_array($thread) && isset($thread['url'])) {
                $normalised[] = [
                    'title' => (string) ($thread['title'] ?? ''),
                    'url' => (string) $thread['url'],
                    'subreddit' => isset($thread['subreddit']) ? (string) $thread['subreddit'] : null,
                ];
            }
        }

        return $normalised;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => InsightKind::class,
            'status' => InsightStatus::class,
            'details' => 'array',
            'read_at' => 'datetime',
        ];
    }
}
