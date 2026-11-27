<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProject;
use Carbon\CarbonInterface;
use Database\Factories\LaunchItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * One venue on one project's launch checklist: its state, its drafted copy
 * and its posts.
 *
 * Whether it is posted is never stored: it is the latest submission, still
 * inside the venue's cooldown. A venue with no cooldown stays posted for
 * good; one with a cooldown comes back on its own once it has elapsed.
 *
 * @property int $id
 * @property int $project_id
 * @property int $launch_venue_id
 * @property Carbon|null $dismissed_at
 * @property string|null $draft
 * @property int|null $agent_run_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LaunchVenue $venue
 * @property-read LaunchSubmission|null $latestSubmission
 * @property-read AgentRun|null $agentRun
 */
#[Fillable(['project_id', 'launch_venue_id', 'dismissed_at', 'draft', 'agent_run_id'])]
class LaunchItem extends Model
{
    /** @use HasFactory<LaunchItemFactory> */
    use BelongsToProject, HasFactory;

    /**
     * Trashed venues included: a removed custom venue or a retired catalog
     * entry still names where the history was posted.
     *
     * @return BelongsTo<LaunchVenue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(LaunchVenue::class, 'launch_venue_id')->withTrashed();
    }

    /**
     * @return HasMany<LaunchSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(LaunchSubmission::class);
    }

    /**
     * @return HasOne<LaunchSubmission, $this>
     */
    public function latestSubmission(): HasOne
    {
        return $this->hasOne(LaunchSubmission::class)->latestOfMany('posted_at');
    }

    /**
     * @return BelongsTo<AgentRun, $this>
     */
    public function agentRun(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class);
    }

    /**
     * When the venue takes a new post, once posted there. Null when it was
     * never posted, or when it takes one post only.
     */
    public function availableAgainOn(): ?CarbonInterface
    {
        if ($this->latestSubmission === null || $this->venue->cooldown_days === null) {
            return null;
        }

        return $this->latestSubmission->posted_at->addDays($this->venue->cooldown_days);
    }

    public function isPosted(): bool
    {
        if ($this->latestSubmission === null) {
            return false;
        }

        return $this->availableAgainOn()?->isFuture() ?? true;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dismissed_at' => 'datetime',
        ];
    }
}
