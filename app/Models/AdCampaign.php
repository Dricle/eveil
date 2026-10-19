<?php

namespace App\Models;

use App\Enums\AdStatus;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\AdCampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One campaign Eveil created, with exactly one ad group on its network:
 * targeting lives here, the copy on its ads. The middle level every network
 * has is the driver's business (`external_ref`).
 *
 * @property int $id
 * @property int $project_id
 * @property int $ad_account_id
 * @property int|null $target_profile_id
 * @property string $name
 * @property string $hypothesis
 * @property array{keywords?: list<string>, negative_keywords?: list<string>, countries?: list<string>, languages?: list<string>} $targeting
 * @property int $daily_budget_micros
 * @property AdStatus $status
 * @property string|null $external_id
 * @property array<string, string>|null $external_ref
 * @property Carbon|null $launched_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'project_id', 'ad_account_id', 'target_profile_id', 'name', 'hypothesis', 'targeting',
    'daily_budget_micros', 'status', 'external_id', 'external_ref', 'launched_at',
])]
class AdCampaign extends Model
{
    /** @use HasFactory<AdCampaignFactory> */
    use BelongsToProject, HasFactory;

    /**
     * @return BelongsTo<AdAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(AdAccount::class, 'ad_account_id');
    }

    /**
     * @return BelongsTo<TargetProfile, $this>
     */
    public function targetProfile(): BelongsTo
    {
        return $this->belongsTo(TargetProfile::class);
    }

    /**
     * @return HasMany<Ad, $this>
     */
    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'targeting' => 'array',
            'daily_budget_micros' => 'integer',
            'status' => AdStatus::class,
            'external_ref' => 'array',
            'launched_at' => 'datetime',
        ];
    }
}
