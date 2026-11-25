<?php

namespace App\Models;

use App\Enums\AdPlatform;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\AdConversionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One goal reached on the user's site (a sign-up, a purchase, whatever they
 * advertise for), reported by their backend with the click id the visitor
 * landed with. Eveil never needs to know what the goal is: it hands the click
 * id to the network it came from, which matches it to the campaign, ad and
 * keyword itself and bids on it from then on.
 *
 * @property int $id
 * @property int $project_id
 * @property AdPlatform $platform
 * @property string $click_id
 * @property Carbon $occurred_at
 * @property int|null $value_micros
 * @property string|null $currency
 * @property Carbon|null $uploaded_at
 * @property string|null $last_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['project_id', 'platform', 'click_id', 'occurred_at', 'value_micros', 'currency', 'uploaded_at', 'last_error'])]
class AdConversion extends Model
{
    /** @use HasFactory<AdConversionFactory> */
    use BelongsToProject, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => AdPlatform::class,
            'occurred_at' => 'datetime',
            'value_micros' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }
}
