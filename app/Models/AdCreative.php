<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProject;
use Database\Factories\AdCreativeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The message, independent of any network: an angle and the copy that carries
 * it. Every ad points at one, so a creative's record reads across networks
 * and "winning on Google, never tried on Microsoft" is a query.
 *
 * Never edited once an ad uses it: a change is a new creative whose
 * `parent_id` is this one, which is how a lineage of variants stays readable.
 *
 * @property int $id
 * @property int $project_id
 * @property int|null $parent_id
 * @property int|null $target_profile_id
 * @property string $angle
 * @property list<string> $headlines
 * @property list<string> $descriptions
 * @property string $final_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['project_id', 'parent_id', 'target_profile_id', 'angle', 'headlines', 'descriptions', 'final_url'])]
class AdCreative extends Model
{
    /** @use HasFactory<AdCreativeFactory> */
    use BelongsToProject, HasFactory;

    /**
     * @return BelongsTo<AdCreative, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
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
            'headlines' => 'array',
            'descriptions' => 'array',
        ];
    }
}
