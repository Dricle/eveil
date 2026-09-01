<?php

namespace App\Models;

use App\Enums\DiscoveryDiagnosis;
use App\Enums\DiscoveryRunOrigin;
use App\Enums\DiscoveryRunStatus;
use App\Enums\DiscoveryTaskKind;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\DiscoveryRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One search for companies matching an target profile. Carries its own hard budget: an
 * unbounded agent loop that fetches pages burns real money, and in
 * cloud that budget IS the credit hold.
 *
 * @property int $id
 * @property int $project_id
 * @property int|null $target_profile_id
 * @property DiscoveryRunOrigin $origin whether an AI search planned this run or a user pasted the links
 * @property DiscoveryRunStatus $status
 * @property array{max_companies: int, max_qualified: int, max_pages: int, max_queries: int} $budget
 * @property int $queries_used
 * @property int $candidates_found
 * @property int $pages_used
 * @property int $qualified_count
 * @property array<string, mixed>|null $stats counters, plus the plan the agent explained before executing
 * @property array<int, array{axis: string, from: mixed, to: mixed, at: string}>|null $relaxations
 * @property DiscoveryDiagnosis|null $diagnosis
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['project_id', 'target_profile_id', 'origin', 'status', 'budget', 'stats', 'relaxations', 'diagnosis', 'started_at', 'finished_at', 'error'])]
class DiscoveryRun extends Model
{
    /** @use HasFactory<DiscoveryRunFactory> */
    use BelongsToProject, HasFactory;

    /**
     * The budget lines, and the counter that spends each one.
     */
    private const COUNTERS = [
        'max_queries' => 'queries_used',
        'max_companies' => 'candidates_found',
        'max_pages' => 'pages_used',
        'max_qualified' => 'qualified_count',
    ];

    /**
     * @return BelongsTo<TargetProfile, $this>
     */
    public function targetProfile(): BelongsTo
    {
        return $this->belongsTo(TargetProfile::class);
    }

    /**
     * @return HasMany<DiscoveryTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(DiscoveryTask::class);
    }

    /**
     * Still in flight: not yet succeeded, exhausted, aborted or failed. What
     * decides whether continuous discovery may start another run for the same
     * profile, so it never has two running at once.
     *
     * @param  Builder<DiscoveryRun>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNotIn('status', [
            DiscoveryRunStatus::Succeeded,
            DiscoveryRunStatus::Exhausted,
            DiscoveryRunStatus::Aborted,
            DiscoveryRunStatus::Failed,
        ]);
    }

    /**
     * Takes one unit off a budget line and says whether it was there to take.
     *
     * One conditional statement, for two reasons. Several workers spend the same
     * envelope at once, so read-then-write would let two of them both see the
     * last page and both fetch it. And the counter must never pass the limit:
     * incrementing first and refusing afterwards would leave a screen reporting
     * "22 searches of 12", which reads as a broken app rather than a cap.
     */
    public function claim(string $line): bool
    {
        $counter = self::COUNTERS[$line];
        $limit = $this->budget[$line] ?? PHP_INT_MAX;

        $claimed = DB::update(
            "update discovery_runs set {$counter} = {$counter} + 1, updated_at = now() where id = ? and {$counter} < ?",
            [$this->id, $limit],
        );

        if ($claimed === 0) {
            return false;
        }

        $this->{$counter}++;

        return true;
    }

    /**
     * What one line of the budget allows, for a node that has to say why it
     * stopped in terms the person reading it can act on.
     */
    public function limit(string $line): int
    {
        return $this->budget[$line] ?? PHP_INT_MAX;
    }

    /**
     * A run is finished when nothing is queued for it any more. Every node asks
     * this on its way out, so whichever one happens to be last closes the run:
     * no supervising job to keep alive, and nothing to poll.
     */
    public function finishIfIdle(): void
    {
        if ($this->status->isTerminal() || $this->tasks()->open()->exists()) {
            return;
        }

        $this->refresh()->update([
            'status' => $this->diagnose() === null ? DiscoveryRunStatus::Succeeded : DiscoveryRunStatus::Exhausted,
            'diagnosis' => $this->diagnose(),
            'stats' => [
                ...$this->stats ?? [],
                'candidates_found' => $this->candidates_found,
                'companies_qualified' => $this->qualified_count,
                // Without these, a dead source and an empty market look
                // identical, and the diagnosis would be confidently wrong
                // about which one happened.
                'source_failures' => $this->failuresOf(DiscoveryTaskKind::Probe),
                'candidate_failures' => [
                    ...$this->failuresOf(DiscoveryTaskKind::Harvest),
                    ...$this->failuresOf(DiscoveryTaskKind::Qualify),
                ],
            ],
            'finished_at' => now(),
        ]);
    }

    /**
     * Why a run came up short decides what should happen next, and one of the
     * answers is "do not widen". Widening a wrong profile produces off-target
     * leads the user then emails, and the complaints land on their own domain.
     */
    private function diagnose(): ?DiscoveryDiagnosis
    {
        // "Too narrow", "wrong source" and "wrong profile" are all verdicts on
        // an AI SEARCH. A user pasting three links they already had is not a
        // narrow market, and diagnosing it as one would also feed a wrong
        // signal into `ContinueDiscovery`'s cadence for the profile.
        if ($this->origin === DiscoveryRunOrigin::Manual) {
            return null;
        }

        if ($this->candidates_found === 0) {
            return DiscoveryDiagnosis::WrongSource;
        }

        if ($this->qualified_count === 0) {
            return DiscoveryDiagnosis::BadTargetProfile;
        }

        return $this->qualified_count < $this->budget['max_qualified'] / 2
            ? DiscoveryDiagnosis::TooNarrow
            : null;
    }

    /**
     * What one kind of node reported going wrong. The detail lives on the task
     * rows; this is the summary the run carries once it is over.
     *
     * @return array<int, string>
     */
    private function failuresOf(DiscoveryTaskKind $kind): array
    {
        return $this->tasks()
            ->where('kind', $kind)
            ->get()
            ->flatMap(fn (DiscoveryTask $task): array => [
                ...$task->result['failures'] ?? [],
                ...array_filter([$task->error]),
            ])
            ->all();
    }

    /**
     * A diagnosed-wrong target profile is escalated to the user, never widened: widening
     * there produces off-target leads the user then emails, and the complaints
     * land on their domain.
     */
    public function mayWiden(): bool
    {
        return $this->diagnosis !== DiscoveryDiagnosis::BadTargetProfile;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origin' => DiscoveryRunOrigin::class,
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
