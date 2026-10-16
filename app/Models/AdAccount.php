<?php

namespace App\Models;

use App\Casts\EncryptedCredential;
use App\Enums\AdAccountStatus;
use App\Enums\AdPlatform;
use App\Enums\AdStatus;
use App\Models\Concerns\BelongsToProject;
use Carbon\CarbonImmutable;
use Database\Factories\AdAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

/**
 * One account on one ad network, owned by the project: the monthly budget is
 * this product's money, so unlike a mailbox it is never shared with another.
 *
 * Eveil only ever touches campaigns it created itself. Whatever the user
 * already runs in the account stays theirs.
 *
 * @property int $id
 * @property int $project_id
 * @property AdPlatform $platform
 * @property int|null $platform_app_id
 * @property string $external_id
 * @property string $name
 * @property string $currency
 * @property string|null $time_zone
 * @property string $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $token_expires_at
 * @property int $monthly_budget_micros
 * @property bool $has_conversion_tracking
 * @property AdAccountStatus $status
 * @property string|null $last_error
 * @property Carbon|null $metrics_synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'project_id', 'platform', 'platform_app_id', 'external_id', 'name', 'currency', 'time_zone',
    'access_token', 'refresh_token', 'token_expires_at',
    'monthly_budget_micros', 'has_conversion_tracking', 'status', 'last_error', 'metrics_synced_at',
])]
#[Hidden(['access_token', 'refresh_token'])]
class AdAccount extends Model
{
    /** @use HasFactory<AdAccountFactory> */
    use BelongsToProject, HasFactory;

    /**
     * @return BelongsTo<PlatformApp, $this>
     */
    public function platformApp(): BelongsTo
    {
        return $this->belongsTo(PlatformApp::class);
    }

    /**
     * @return HasMany<AdCampaign, $this>
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(AdCampaign::class);
    }

    /**
     * @return HasManyThrough<Ad, AdCampaign, $this>
     */
    public function ads(): HasManyThrough
    {
        return $this->hasManyThrough(Ad::class, AdCampaign::class);
    }

    /**
     * Spent since the first of the month, in micros. The month is the
     * account's own, in its time zone, because that is the one its invoice
     * uses.
     */
    public function spentThisMonthMicros(): int
    {
        return (int) AdMetric::query()
            ->whereIn('ad_id', Ad::query()->whereIn('ad_campaign_id', $this->campaigns()->select('id'))->select('id'))
            ->where('date', '>=', $this->today()->startOfMonth()->toDateString())
            ->sum('cost_micros');
    }

    /**
     * What the campaigns running now would spend in a day.
     */
    public function activeDailyBudgetMicros(): int
    {
        return (int) $this->campaigns()->where('status', AdStatus::Active)->sum('daily_budget_micros');
    }

    public function today(): CarbonImmutable
    {
        return now($this->time_zone ?? config('app.timezone'));
    }

    public function isUsable(): bool
    {
        return $this->status === AdAccountStatus::Active && $this->monthly_budget_micros > 0;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => AdPlatform::class,
            'access_token' => EncryptedCredential::class,
            'refresh_token' => EncryptedCredential::class,
            'token_expires_at' => 'datetime',
            'monthly_budget_micros' => 'integer',
            'has_conversion_tracking' => 'boolean',
            'status' => AdAccountStatus::class,
            'metrics_synced_at' => 'datetime',
        ];
    }
}
