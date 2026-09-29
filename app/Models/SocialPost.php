<?php

namespace App\Models;

use App\Enums\SocialPlatform;
use App\Enums\SocialPostSourceType;
use App\Enums\SocialPostStatus;
use App\Enums\SocialPostVariant;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\SocialPostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One drafted or published LinkedIn, X or Bluesky post. Project-scoped,
 * unlike the account it posts through: the content belongs to one product's
 * voice, not to the organization.
 *
 * `evidence` is what grounded the draft, shown beside the body in the queue.
 * A `client_won` draft is written as a NAMED and an ANONYMIZED sibling in one
 * call, sharing `source_type`/`source_ref`: approving one rejects the other.
 *
 * @property int $id
 * @property int $project_id
 * @property SocialPlatform $platform
 * @property int|null $social_account_id
 * @property int|null $agent_run_id
 * @property SocialPostSourceType $source_type
 * @property string|null $source_ref
 * @property SocialPostVariant|null $variant
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
    'source_type', 'source_ref', 'variant', 'evidence', 'body',
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
     * The other half of a named/anonymized pair, still a draft.
     *
     * @return Builder<SocialPost>
     */
    public function sibling(): Builder
    {
        return SocialPost::query()
            ->where('platform', $this->platform)
            ->where('source_type', $this->source_type)
            ->where('source_ref', $this->source_ref)
            ->whereNotNull('variant')
            ->where('variant', '!=', $this->variant)
            ->whereKeyNot($this->id)
            ->where('status', SocialPostStatus::Draft);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => SocialPlatform::class,
            'source_type' => SocialPostSourceType::class,
            'variant' => SocialPostVariant::class,
            'status' => SocialPostStatus::class,
            'published_at' => 'datetime',
            'promoted_at' => 'datetime',
            'stats_checked_at' => 'datetime',
        ];
    }
}
