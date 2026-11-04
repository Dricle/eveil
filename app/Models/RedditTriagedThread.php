<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProject;
use Database\Factories\RedditTriagedThreadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A Reddit permalink the opportunity triage already turned down, written by
 * `App\Jobs\ScanRedditOpportunities`. Lets both finders skip it instead of
 * paying the triage to reject it again on every scan.
 *
 * @property int $id
 * @property int $project_id
 * @property string $permalink
 * @property Carbon $triaged_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['project_id', 'permalink', 'triaged_at'])]
class RedditTriagedThread extends Model
{
    /** @use HasFactory<RedditTriagedThreadFactory> */
    use BelongsToProject, HasFactory;

    /**
     * A thread can change (new comments, a new question), so a rejection
     * expires and the thread may be judged once more.
     */
    public const TTL_DAYS = 60;

    /**
     * Every permalink a scan must not hand to the triage again: one already
     * drafted, or one rejected within the TTL. Project-scoped through the
     * global scope, so call it inside `CurrentProject::run()`.
     *
     * @return Collection<int, string>
     */
    public static function alreadySeen(): Collection
    {
        return RedditReply::query()->pluck('thread_permalink')
            ->merge(static::query()->where('triaged_at', '>=', now()->subDays(self::TTL_DAYS))->pluck('permalink'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'triaged_at' => 'datetime',
        ];
    }
}
