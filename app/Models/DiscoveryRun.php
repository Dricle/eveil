<?php

namespace App\Models;

use App\Enums\DiscoveryDiagnosis;
use App\Enums\DiscoveryRunStatus;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\DiscoveryRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One search for companies matching an ICP. Carries its own hard budget: an
 * unbounded agent loop that fetches pages burns real money (ADR-004), and in
 * cloud that budget IS the credit hold (ADR-019).
 *
 * @property int $id
 * @property int $project_id
 * @property int|null $icp_id
 * @property DiscoveryRunStatus $status
 * @property array{max_companies: int, max_qualified: int, max_pages: int, max_queries: int} $budget
 * @property array<string, mixed>|null $stats counters, plus the plan the agent explained before executing
 * @property array<int, array{axis: string, from: mixed, to: mixed, at: string}>|null $relaxations
 * @property DiscoveryDiagnosis|null $diagnosis
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['project_id', 'icp_id', 'status', 'budget', 'stats', 'relaxations', 'diagnosis', 'started_at', 'finished_at', 'error'])]
class DiscoveryRun extends Model
{
    /** @use HasFactory<DiscoveryRunFactory> */
    use BelongsToProject, HasFactory;

    /**
     * @return BelongsTo<Icp, $this>
     */
    public function icp(): BelongsTo
    {
        return $this->belongsTo(Icp::class);
    }

    /**
     * A diagnosed-wrong ICP is escalated to the user, never widened: widening
     * there produces off-target leads the user then emails, and the complaints
     * land on their domain (ADR-020).
     */
    public function mayWiden(): bool
    {
        return $this->diagnosis !== DiscoveryDiagnosis::BadIcp;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DiscoveryRunStatus::class,
            'diagnosis' => DiscoveryDiagnosis::class,
            'budget' => 'array',
            'stats' => 'array',
            'relaxations' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
