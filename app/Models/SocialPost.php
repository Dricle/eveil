<?php

namespace App\Models;

use App\Enums\SocialPlatform;
use App\Enums\SocialPostSourceType;
use App\Enums\SocialPostStatus;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\SocialPostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One drafted or published X or Bluesky post. Project-scoped, like
 * `LinkedinPost`: the content belongs to one product's voice.
 *
 * @property int $id
 * @property int $project_id
 * @property SocialPlatform $platform
 * @property int|null $social_account_id
 * @property int|null $agent_run_id
 * @property SocialPostSourceType $source_type
 * @property string|null $source_ref
 * @property string $evidence
 * @property string $body
 * @property SocialPostStatus $status
 * @property string|null $rejection_reason
 * @property string|null $external_id
 * @property string|null $url
 * @property Carbon|null $published_at
 * @property string|null $last_error
 * @property Carbon|null $promoted_at
 * @property int $likes_count
 * @property Carbon|null $stats_checked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'project_id', 'platform', 'social_account_id', 'agent_run_id',
    'source_type', 'source_ref', 'evidence', 'body',
    'status', 'rejection_reason', 'external_id', 'url', 'published_at', 'last_error',
    'promoted_at', 'likes_count', 'stats_checked_at',
])]
class SocialPost extends Model
{
    /** @use HasFactory<SocialPostFactory> */
    use BelongsToProject, HasFactory;

    /**
     * @return BelongsTo<SocialAccount, $this>
     */
    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    /**
     * @return BelongsTo<AgentRun, $this>
     */
    public function agentRun(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => SocialPlatform::class,
            'source_type' => SocialPostSourceType::class,
            'status' => SocialPostStatus::class,
            'published_at' => 'datetime',
            'promoted_at' => 'datetime',
            'stats_checked_at' => 'datetime',
        ];
    }
}
