<?php

namespace App\Models;

use App\Enums\LinkedinPostSourceType;
use App\Enums\LinkedinPostStatus;
use App\Enums\LinkedinPostVariant;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\LinkedinPostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One drafted or published LinkedIn post. Project-scoped, unlike the account
 * it posts through: the content itself belongs to one product's voice, not
 * to the organization.
 *
 * `evidence` is what grounded the draft - the same "evidence or nothing"
 * discipline as `CompanyTargetEvaluation.fit_reason` - and is shown beside
 * the body in the approval queue rather than hidden in an agent_runs payload
 * nobody reads.
 *
 * A `client_won` draft is written as a NAMED and an ANONYMIZED sibling in
 * one agent call, sharing `source_type`/`source_ref`: the user picks which
 * one goes out, and approving one rejects the other (`variant`).
 *
 * @property int $id
 * @property int $project_id
 * @property int|null $linkedin_account_id
 * @property int|null $agent_run_id
 * @property LinkedinPostSourceType $source_type
 * @property string|null $source_ref
 * @property LinkedinPostVariant|null $variant
 * @property string $evidence
 * @property string $body
 * @property LinkedinPostStatus $status
 * @property string|null $rejection_reason
 * @property string|null $urn
 * @property Carbon|null $published_at
 * @property string|null $last_error
 * @property Carbon|null $promoted_at
 * @property int $likes_count
 * @property Carbon|null $stats_checked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'project_id', 'linkedin_account_id', 'agent_run_id',
    'source_type', 'source_ref', 'variant', 'evidence', 'body',
    'status', 'rejection_reason', 'urn', 'published_at', 'last_error',
    'promoted_at', 'likes_count', 'stats_checked_at',
])]
class LinkedinPost extends Model
{
    /** @use HasFactory<LinkedinPostFactory> */
    use BelongsToProject, HasFactory;

    /**
     * @return BelongsTo<LinkedinAccount, $this>
     */
    public function linkedinAccount(): BelongsTo
    {
        return $this->belongsTo(LinkedinAccount::class);
    }

    /**
     * @return BelongsTo<AgentRun, $this>
     */
    public function agentRun(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class);
    }

    /**
     * The other half of a named/anonymized pair, still a draft. Approving
     * one rejects its sibling: only one of a pair can ever be published.
     *
     * @return Builder<LinkedinPost>
     */
    public function sibling(): Builder
    {
        return LinkedinPost::query()
            ->where('source_type', $this->source_type)
            ->where('source_ref', $this->source_ref)
            ->where('variant', '!=', $this->variant)
            ->whereNotNull('variant')
            ->whereKeyNot($this->id)
            ->where('status', LinkedinPostStatus::Draft);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_type' => LinkedinPostSourceType::class,
            'variant' => LinkedinPostVariant::class,
            'status' => LinkedinPostStatus::class,
            'published_at' => 'datetime',
            'promoted_at' => 'datetime',
            'stats_checked_at' => 'datetime',
        ];
    }
}
