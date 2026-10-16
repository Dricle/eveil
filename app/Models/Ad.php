<?php

namespace App\Models;

use App\Enums\AdStatus;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\AdFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One creative running in one campaign.
 *
 * @property int $id
 * @property int $project_id
 * @property int $ad_campaign_id
 * @property int $ad_creative_id
 * @property AdStatus $status
 * @property string|null $external_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['project_id', 'ad_campaign_id', 'ad_creative_id', 'status', 'external_id'])]
class Ad extends Model
{
    /** @use HasFactory<AdFactory> */
    use BelongsToProject, HasFactory;

    /**
     * @return BelongsTo<AdCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'ad_campaign_id');
    }

    /**
     * @return BelongsTo<AdCreative, $this>
     */
    public function creative(): BelongsTo
    {
        return $this->belongsTo(AdCreative::class, 'ad_creative_id');
    }

    /**
     * @return HasMany<AdMetric, $this>
     */
    public function metrics(): HasMany
    {
        return $this->hasMany(AdMetric::class);
    }

    /**
     * Lifetime totals as `impressions`, `clicks`, `cost_micros` and
     * `conversions` attributes, summed by the database.
     *
     * @param  Builder<Ad>  $query
     */
    #[Scope]
    protected function withTotals(Builder $query): void
    {
        $query
            ->withSum('metrics as impressions', 'impressions')
            ->withSum('metrics as clicks', 'clicks')
            ->withSum('metrics as cost_micros', 'cost_micros')
            ->withSum('metrics as conversions', 'conversions');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AdStatus::class,
        ];
    }
}
