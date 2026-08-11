<?php

namespace App\Models;

use Database\Factories\StepVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A null `language` means the body is generated per lead in the prospect's own
 * language; a value marks a hand-written or translated variant, cached per
 * (template, language) pair (ADR-021).
 *
 * @property int $id
 * @property int $campaign_step_id
 * @property string $subject
 * @property string $body
 * @property string|null $language
 * @property int $weight
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['campaign_step_id', 'subject', 'body', 'language', 'weight'])]
class StepVariant extends Model
{
    /** @use HasFactory<StepVariantFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<CampaignStep, $this>
     */
    public function step(): BelongsTo
    {
        return $this->belongsTo(CampaignStep::class, 'campaign_step_id');
    }
}
