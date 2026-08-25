<?php

namespace App\Cloud\Models;

use App\Models\AgentRun;
use App\Models\Organization;
use Database\Factories\CreditTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The permanent ledger a balance column alone can't be: every grant and
 * debit, at the rate actually charged, whether it came from a webhook (and
 * which one, for idempotency) or from an agent call.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $type
 * @property int $credits
 * @property string|null $agent
 * @property int|null $agent_run_id
 * @property string|null $stripe_event_id
 * @property Carbon|null $created_at
 */
#[Fillable(['organization_id', 'type', 'credits', 'agent', 'agent_run_id', 'stripe_event_id'])]
class CreditTransaction extends Model
{
    /** @use HasFactory<CreditTransactionFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected static function newFactory(): CreditTransactionFactory
    {
        return CreditTransactionFactory::new();
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<AgentRun, $this>
     */
    public function agentRun(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class);
    }
}
