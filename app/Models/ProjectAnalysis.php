<?php

namespace App\Models;

use App\Enums\AnalysisStatus;
use App\Enums\AnalysisType;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\ProjectAnalysisFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * History of what the Website agent produced, so a re-run can be diffed against
 * the previous one.
 *
 * @property int $id
 * @property int $project_id
 * @property int|null $agent_run_id
 * @property int|null $code_repository_id
 * @property AnalysisType $type
 * @property AnalysisStatus $status
 * @property array<string, mixed>|null $raw
 * @property array<string, mixed>|null $summary
 * @property array<int, array{url: string, reason: string}>|null $failures
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['project_id', 'agent_run_id', 'code_repository_id', 'type', 'status', 'raw', 'summary', 'failures', 'error'])]
class ProjectAnalysis extends Model
{
    /** @use HasFactory<ProjectAnalysisFactory> */
    use BelongsToProject, HasFactory;

    /**
     * @return BelongsTo<AgentRun, $this>
     */
    public function agentRun(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class);
    }

    /**
     * Which repository this run read. Null for a website analysis — and the
     * reason repositories are their own table: with several per project,
     * `type = repo` no longer says what was analysed.
     *
     * @return BelongsTo<CodeRepository, $this>
     */
    public function codeRepository(): BelongsTo
    {
        return $this->belongsTo(CodeRepository::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AnalysisType::class,
            'status' => AnalysisStatus::class,
            'raw' => 'array',
            'summary' => 'array',
            'failures' => 'array',
        ];
    }
}
