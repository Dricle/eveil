<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $target_profile_id
 * @property string $name
 * @property CampaignStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['project_id', 'target_profile_id', 'name', 'status'])]
class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use BelongsToProject, HasFactory;

    /**
     * @return HasMany<CampaignStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(CampaignStep::class)->orderBy('position');
    }

    /**
     * The segment this sequence was written for. Null once somebody composes a
     * campaign by hand: it then answers to no profile, which is allowed.
     *
     * @return BelongsTo<TargetProfile, $this>
     */
    public function targetProfile(): BelongsTo
    {
        return $this->belongsTo(TargetProfile::class);
    }

    /**
     * @return HasMany<CampaignLead, $this>
     */
    public function campaignLeads(): HasMany
    {
        return $this->hasMany(CampaignLead::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
        ];
    }
}
