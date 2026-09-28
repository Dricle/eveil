<?php

namespace App\Models;

use App\Enums\SocialPlatform;
use App\Enums\SocialPostExampleSource;
use Database\Factories\SocialPostExampleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A post worth learning from, fed back into `SocialPostWriter` for every
 * project writing for that network. One bank per network: what works in 280
 * characters on X is not what works in a LinkedIn post.
 *
 * Shared instance-wide on purpose, not scoped to an organization or project,
 * same reasoning as `EmailExample`. Fed by exactly two trusted sources: a
 * superadmin typing one in, or `FetchSocialPostStats` crossing a real,
 * externally-measured like count. A user's own "mark as successful" click
 * never writes here - it only stamps `social_posts.promoted_at`, feeding that
 * same project's own prompts - which is what keeps a self-serve click from
 * being a one-click poisoning vector into every other tenant's prompt.
 *
 * @property int $id
 * @property SocialPlatform $platform
 * @property string $body
 * @property SocialPostExampleSource $source
 * @property int|null $social_post_id
 * @property int|null $added_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['platform', 'body', 'source', 'social_post_id', 'added_by_user_id'])]
class SocialPostExample extends Model
{
    /** @use HasFactory<SocialPostExampleFactory> */
    use HasFactory;

    public const SAMPLE_SIZE = 10;

    /**
     * @return BelongsTo<SocialPost, $this>
     */
    public function socialPost(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }

    /**
     * A random few-shot section for one network's writer. Empty while that
     * network's bank is, so nothing changes until a first example exists.
     */
    public static function promptDigest(SocialPlatform $platform, int $limit = self::SAMPLE_SIZE): string
    {
        $examples = static::query()->where('platform', $platform)->inRandomOrder()->limit($limit)->pluck('body');

        if ($examples->isEmpty()) {
            return '';
        }

        return "## Proven {$platform->label()} posts from across Eveil\n\n".$examples->implode("\n\n---\n\n");
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => SocialPlatform::class,
            'source' => SocialPostExampleSource::class,
        ];
    }
}
