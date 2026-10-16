<?php

namespace App\Models;

use Database\Factories\AdMetricFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One ad's numbers for one day, as its network reports them. Upserted on
 * `(ad_id, date)`: networks revise the last few days as late conversions
 * come in, so a day is read again rather than appended.
 *
 * @property int $id
 * @property int $ad_id
 * @property Carbon $date
 * @property int $impressions
 * @property int $clicks
 * @property int $cost_micros
 * @property float $conversions
 * @property float $conversion_value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['ad_id', 'date', 'impressions', 'clicks', 'cost_micros', 'conversions', 'conversion_value'])]
class AdMetric extends Model
{
    /** @use HasFactory<AdMetricFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Ad, $this>
     */
    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'impressions' => 'integer',
            'clicks' => 'integer',
            'cost_micros' => 'integer',
            'conversions' => 'float',
            'conversion_value' => 'float',
        ];
    }
}
