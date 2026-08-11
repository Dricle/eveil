<?php

namespace App\Models;

use App\Enums\CampaignStepType;
use Database\Factories\CampaignStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $campaign_id
 * @property int $position
 * @property CampaignStepType $type
 * @property int|null $delay_hours
 * @property array<string, mixed>|null $config
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['campaign_id', 'position', 'type', 'delay_hours', 'config'])]
class CampaignStep extends Model
{
    /** @use HasFactory<CampaignStepFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return HasMany<StepVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(StepVariant::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CampaignStepType::class,
            'config' => 'array',
        ];
    }
}
