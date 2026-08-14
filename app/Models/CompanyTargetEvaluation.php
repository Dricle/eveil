<?php

namespace App\Models;

use Database\Factories\CompanyTargetEvaluationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Fit is a property of the (company, target profile) pair, never of the company alone.
 * `fit_reason` doubles as the opening hook at personalisation time.
 *
 * @property int $id
 * @property int $company_id
 * @property int $target_profile_id
 * @property int|null $discovery_run_id
 * @property int $fit_score
 * @property string $fit_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['company_id', 'target_profile_id', 'discovery_run_id', 'fit_score', 'fit_reason'])]
class CompanyTargetEvaluation extends Model
{
    /** @use HasFactory<CompanyTargetEvaluationFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<TargetProfile, $this>
     */
    public function targetProfile(): BelongsTo
    {
        return $this->belongsTo(TargetProfile::class);
    }
}
