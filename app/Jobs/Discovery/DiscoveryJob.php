<?php

namespace App\Jobs\Discovery;

use App\Enums\AgentRunStatus;
use App\Enums\DiscoveryRunStatus;
use App\Enums\DiscoveryTaskKind;
use App\Enums\DiscoveryTaskStatus;
use App\Models\AgentRun;
use App\Models\DiscoveryRun;
use App\Models\DiscoveryTask;
use App\Services\Discovery\Candidate;
use App\Services\Discovery\Qualifier;
use App\Support\CurrentProject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * What every node of a discovery run does around its own work: read the run
 * before starting, write its own row, and never take the run down with it.
 *
 * Discovery fans out into queued jobs rather than one long agent tool loop
 * because a loop's cost grows quadratically with its depth: every step resends
 * the whole history. Flat nodes also replay one at a time, skip work already
 * done, and keep a crash at step 35 from losing the previous 34.
 */
abstract class DiscoveryJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public DiscoveryTask $task)
    {
        $this->onQueue('discovery');
    }

    public function handle(CurrentProject $currentProject): void
    {
        $task = $this->task->refresh();
        $run = $task->discoveryRun;

        // One flag carries both the credit ceiling and the cancel button:
        // whatever is already queued reads it on pickup and deletes itself.
        // No job registry to keep, no worker to kill.
        if ($run->status->isTerminal()) {
            $this->close($task, DiscoveryTaskStatus::Skipped, ['failures' => ['the run had already stopped']]);

            return;
        }

        $task->update([
            'status' => DiscoveryTaskStatus::Running,
            'attempts' => $task->attempts + 1,
            'started_at' => now(),
            'error' => null,
        ]);

        try {
            $result = $currentProject->run($task->project, fn (): array => $this->execute($run, $task));

            $this->close($task, DiscoveryTaskStatus::Succeeded, $result);
        } catch (TaskSkipped $e) {
            $this->close($task, DiscoveryTaskStatus::Skipped, ['failures' => [$e->getMessage()]]);
        } catch (Throwable $e) {
            // A failing node fails its own row, never the run: one unreadable
            // directory must not cost the companies already found.
            $this->close($task, DiscoveryTaskStatus::Failed, null, $e->getMessage());

            if ($this->failsRun()) {
                $run->update([
                    'status' => DiscoveryRunStatus::Failed,
                    'error' => $e->getMessage(),
                    'finished_at' => now(),
                ]);

                return;
            }

            report($e);
        }

        $run->refresh()->finishIfIdle();
    }

    /**
     * The work itself. What it returns lands on the row as the node's result:
     * counters and reasons, never a page body.
     *
     * @return array<string, mixed>
     */
    abstract protected function execute(DiscoveryRun $run, DiscoveryTask $task): array;

    /**
     * Whether this node dying means the run cannot go on. True only for
     * planning: with no plan there is nowhere to look.
     */
    protected function failsRun(): bool
    {
        return false;
    }

    /**
     * @throws TaskSkipped
     */
    protected function skip(string $why): never
    {
        throw new TaskSkipped($why);
    }

    /**
     * Hands work to the next node. The row is written before the job is queued,
     * so a run is never briefly idle-looking while its children are in flight.
     *
     * @param  array<string, mixed>  $payload
     * @param  class-string<DiscoveryJob>  $job
     */
    protected function fork(DiscoveryTask $parent, DiscoveryTaskKind $kind, array $payload, string $job): void
    {
        $job::dispatch(DiscoveryTask::create([
            'project_id' => $parent->project_id,
            'discovery_run_id' => $parent->discovery_run_id,
            'parent_id' => $parent->id,
            'kind' => $kind,
            'status' => DiscoveryTaskStatus::Pending,
            'payload' => $payload,
        ]));
    }

    /**
     * Queues one qualification per candidate worth paying for, and stops at the
     * candidate ceiling. Both the probes and the directory harvests end here.
     *
     * @param  iterable<int, Candidate>  $candidates
     */
    protected function queueQualifications(DiscoveryRun $run, DiscoveryTask $task, iterable $candidates): int
    {
        $queued = 0;

        foreach ($candidates as $candidate) {
            $payload = $candidate->toArray();
            $domain = $payload['domain'];

            // No site of its own, which is ordinary on a directory listing.
            // Worth qualifying only when the listing also published an address:
            // with nothing to fetch and nothing to send, reading it is a model
            // call spent on a row that can never be contacted.
            if ($domain === null && ($payload['facts']['email'] ?? null) === null) {
                continue;
            }

            // Already found for this project, or already queued by another node
            // of this run. Both checks happen before the budget is touched: a
            // company we have costs neither a page fetch nor a model call, and
            // is not a candidate this run found either.
            if (Qualifier::existing($task->project_id, $candidate) !== null) {
                continue;
            }

            $queuedAlready = $run->tasks()
                ->where('kind', DiscoveryTaskKind::Qualify)
                ->when(
                    $domain !== null,
                    fn (Builder $query) => $query->where('payload->domain', $domain),
                    fn (Builder $query) => $query->whereNull('payload->domain')->where('payload->name', $payload['name']),
                )
                ->exists();

            if ($queuedAlready) {
                continue;
            }

            if (! $run->claim('max_companies')) {
                break;
            }

            $this->fork($task, DiscoveryTaskKind::Qualify, $payload, QualifyCandidate::class);
            $queued++;
        }

        return $queued;
    }

    /**
     * The row for the model call this node is about to make, opened before the
     * call so the graph shows the node as costing something while it runs.
     */
    protected function meter(DiscoveryTask $task, string $agent): AgentRun
    {
        $run = AgentRun::create([
            'project_id' => $task->project_id,
            'agent' => $agent,
            'status' => AgentRunStatus::Pending,
        ]);

        $task->update(['agent_run_id' => $run->id]);

        return $run;
    }

    /**
     * @param  array<string, mixed>|null  $result
     */
    private function close(DiscoveryTask $task, DiscoveryTaskStatus $status, ?array $result = null, ?string $error = null): void
    {
        $task->update([
            'status' => $status,
            'result' => $result,
            'error' => $error,
            'finished_at' => now(),
        ]);
    }
}
