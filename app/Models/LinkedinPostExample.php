<?php

namespace App\Models;

use App\Enums\LinkedinPostExampleSource;
use Database\Factories\LinkedinPostExampleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A LinkedIn post's text worth learning from, fed back into `LinkedinPostWriter`
 * for every project.
 *
 * Shared instance-wide on purpose, not scoped to an organization or project -
 * same reasoning as `EmailExample`. Fed by exactly two trusted sources: a
 * superadmin typing one in, or `FetchLinkedinPostStats` crossing a real,
 * externally-measured like count. A user's own "mark as successful" click
 * never writes here - it only stamps `linkedin_posts.promoted_at`, feeding
 * that same project's own future prompts, which is what keeps a self-serve
 * click from being a one-click poisoning vector into every other tenant's
 * prompt.
 *
 * @property int $id
 * @property string $body
 * @property LinkedinPostExampleSource $source
 * @property int|null $linkedin_post_id
 * @property int|null $added_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['body', 'source', 'linkedin_post_id', 'added_by_user_id'])]
class LinkedinPostExample extends Model
{
    /** @use HasFactory<LinkedinPostExampleFactory> */
    use HasFactory;

    public const SAMPLE_SIZE = 10;

    /**
     * @return BelongsTo<LinkedinPost, $this>
     */
    public function linkedinPost(): BelongsTo
    {
        return $this->belongsTo(LinkedinPost::class);
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

        return "## Proven LinkedIn posts from across Eveil\n\n".$examples->implode("\n\n---\n\n");
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => LinkedinPostExampleSource::class,
        ];
    }
}
