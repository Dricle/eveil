<?php

namespace App\Models;

use App\Enums\AgentRunStatus;
use App\Enums\AgentType;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\AgentRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Every agent invocation lands here (ADR-004): debug log, analysis history and
 * billing meter in one table, in BOTH editions — only the credit ledger is
 * cloud-only.
 *
 * Retention is split (ADR-018): payloads carry names and emails and are purged
 * at 90 days, metrics survive so billing history stays intact.
 *
 * @property int $id
 * @property int $project_id
 * @property AgentType $type
 * @property AgentRunStatus $status
 * @property string|null $provider
 * @property string|null $model
 * @property array<string, mixed>|null $input
 * @property array<string, mixed>|null $output
 * @property Carbon|null $payloads_purged_at
 * @property int $tokens_in
 * @property int $tokens_out
 * @property string $cost
 * @property int|null $duration_ms
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['project_id', 'type', 'status', 'provider', 'model', 'input', 'output', 'tokens_in', 'tokens_out', 'cost', 'duration_ms', 'error'])]
class AgentRun extends Model
{
    /** @use HasFactory<AgentRunFactory> */
    use BelongsToProject, HasFactory;

    /**
     * Drops the payloads while keeping every metric — the shape ADR-018 asks
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
            'type' => AgentType::class,
            'status' => AgentRunStatus::class,
            'input' => 'array',
            'output' => 'array',
            'payloads_purged_at' => 'datetime',
        ];
    }
}
