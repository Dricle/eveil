<?php

namespace App\Models;

use App\Enums\AgentRunStatus;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\AgentRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Every agent invocation lands here: debug log, analysis history and
 * billing meter in one table, in BOTH editions. Only the credit ledger is
 * cloud-only.
 *
 * Retention is split: payloads carry names and emails and are purged
 * at 90 days, metrics survive so billing history stays intact.
 *
 * @property int $id
 * @property int $project_id
 * @property string $agent
 * @property AgentRunStatus $status
 * @property string|null $provider
 * @property string|null $model
 * @property array<string, mixed>|null $input
 * @property array<string, mixed>|null $output
 * @property Carbon|null $payloads_purged_at
 * @property int $tokens_in
 * @property int $tokens_out
 * @property int|null $duration_ms
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['project_id', 'agent', 'status', 'provider', 'model', 'input', 'output', 'tokens_in', 'tokens_out', 'duration_ms', 'error'])]
class AgentRun extends Model
{
    /** @use HasFactory<AgentRunFactory> */
    use BelongsToProject, HasFactory;

    /**
     * How long a run may sit unfinished before a screen stops calling it live.
     * Past it the worker is gone, the queue is not being drained, or the whole
     * instance was redeployed mid-call: in every case nothing is coming back.
     */
    private const STALE_AFTER_MINUTES = 15;

    /**
     * The last thing one agent did for this project, which is what a screen
     * asks about: is the work I started still coming, and did it fail?
     *
     * @param  Builder<AgentRun>  $query
     */
    #[Scope]
    protected function latestFor(Builder $query, string $agent): void
    {
        $query->where('agent', $agent)->latest('id');
    }

    /**
     * Queued or mid-call, and recent enough to still be believed.
     */
    public function isInFlight(): bool
    {
        return $this->status->isInFlight()
            && $this->created_at?->isAfter(now()->subMinutes(self::STALE_AFTER_MINUTES));
    }

    /**
     * Drops the payloads while keeping every metric. The shape the retention rule asks
     * for, so purging leads never leaves personal data behind in the meter.
     */
    public function purgePayloads(): void
    {
        $this->forceFill([
            'input' => null,
            'output' => null,
            'payloads_purged_at' => now(),
        ])->save();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AgentRunStatus::class,
            'input' => 'array',
            'output' => 'array',
            'payloads_purged_at' => 'datetime',
        ];
    }
}
