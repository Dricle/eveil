<?php

namespace App\Models;

use App\Enums\DiscoveryTaskKind;
use App\Enums\DiscoveryTaskStatus;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\DiscoveryTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One node of a discovery run: what it was asked to do, what it produced, what
 * it cost, and how it failed. The row is what the screen draws and what a
 * replay re-reads — the job carries no state of its own.
 *
 * @property int $id
 * @property int $project_id
 * @property int $discovery_run_id
 * @property int|null $parent_id
 * @property DiscoveryTaskKind $kind
 * @property DiscoveryTaskStatus $status
 * @property array<string, mixed>|null $payload
 * @property array<string, mixed>|null $result
 * @property int|null $agent_run_id
 * @property int $attempts
 * @property string|null $error
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['project_id', 'discovery_run_id', 'parent_id', 'kind', 'status', 'payload', 'result', 'agent_run_id', 'attempts', 'error', 'started_at', 'finished_at'])]
class DiscoveryTask extends Model
{
    /** @use HasFactory<DiscoveryTaskFactory> */
    use BelongsToProject, HasFactory;

    /**
     * @return BelongsTo<DiscoveryRun, $this>
     */
    public function discoveryRun(): BelongsTo
    {
        return $this->belongsTo(DiscoveryRun::class);
    }

    /**
     * The model call this node paid for, when it made one. Most nodes do not:
     * the agent decides where to look, PHP does the volume.
     *
     * @return BelongsTo<AgentRun, $this>
     */
    public function agentRun(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class);
    }

    /**
     * @return HasMany<DiscoveryTask, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(DiscoveryTask::class, 'parent_id');
    }

    /**
     * Queued or running — what "is this run still working?" comes down to.
     *
     * @param  Builder<DiscoveryTask>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereIn('status', [DiscoveryTaskStatus::Pending, DiscoveryTaskStatus::Running]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => DiscoveryTaskKind::class,
            'status' => DiscoveryTaskStatus::class,
            'payload' => 'array',
            'result' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
