<?php

namespace App\Ai;

use App\Ai\Agents\EveilAgent;
use App\Ai\Contracts\SpendGuardInterface;
use App\Enums\AgentRunStatus;
use App\Models\AgentRun;
use Laravel\Ai\Events\AgentFailed;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Responses\StructuredAgentResponse;

/**
 * Every agent invocation lands in `agent_runs`: the debug log, the analysis
 * history and the billing meter are the same table.
 *
 * Run-level event listeners rather than agent middleware: since laravel/ai 1.0
 * middleware wraps each generation step, so a run with tool calls would pass
 * through it several times, and a step never knows it is the last one. The
 * events bracket the whole run, and `AgentFailed` fires once failover has run
 * out of providers, so a throwing provider is still recorded as failed instead
 * of leaving a row stuck on "running".
 *
 * A singleton: it holds the rows of the runs in flight, keyed by invocation id,
 * which stays the same across failover attempts.
 */
class RecordsAgentRun
{
    /** @var array<string, array{run: AgentRun, startedAt: float}> */
    private array $running = [];

    /**
     * Fired once per provider attempt, so a failover lands on the row the
     * first attempt opened rather than a second one.
     */
    public function start(PromptingAgent $event): void
    {
        $agent = $event->prompt->agent;

        if (! $agent instanceof EveilAgent) {
            return;
        }

        $attributes = [
            'project_id' => $agent->project->id,
            'agent' => $agent::slug(),
            // The SDK's own id for this invocation, one per run and the same
            // one across every failover attempt. Nothing here joins on it: it
            // is what makes the step and tool events, which persist nowhere,
            // findable next to the row they belong to.
            'invocation_id' => $event->invocationId,
            'status' => AgentRunStatus::Running,
            // Who was ASKED. Failover can answer from somebody else, so this is
            // overwritten on success with whoever actually did.
            'provider' => $event->prompt->provider->name(),
            'model' => $event->prompt->model,
            'input' => $this->storable(['prompt' => $event->prompt->prompt]),
        ];

        // A run queued from a screen already has its row, opened as `pending`
        // at dispatch so the page could report the work before a worker existed
        // to do it. Claim it rather than opening a second one: one invocation
        // is one row, and the meter is that count.
        $run = $this->running[$event->invocationId]['run'] ?? $agent->run;

        if ($run === null) {
            $run = AgentRun::create($attributes);
        } else {
            $run->update($attributes);
        }

        $this->running[$event->invocationId] ??= ['run' => $run, 'startedAt' => microtime(true)];

        // Asked here, and only here, because this is the one place every agent
        // invocation passes through. A discovery run queues dozens of
        // qualifications and dozens of contact extractions with no screen in
        // between, so a check at the button would stop nothing.
        //
        // Thrown before the provider is called; `failed()` marks the row,
        // since screens poll it to know whether work is still coming, and a
        // `pending` row nobody ever finishes spins a spinner for ever.
        $refusal = app(SpendGuardInterface::class)->refusal($agent->project, $agent::slug());

        if ($refusal !== null) {
            throw new OutOfCredit($refusal);
        }
    }

    public function succeeded(AgentPrompted $event): void
    {
        $agent = $event->prompt->agent;
        $running = $this->pull($event->invocationId);

        if (! $agent instanceof EveilAgent || $running === null) {
            return;
        }

        $response = $event->response;

        $running['run']->update([
            'status' => AgentRunStatus::Succeeded,
            // The provider and model that ANSWERED, which after a failover
            // is not the one that was asked. Recording the request meant
            // the meter attributed a run to a provider that never billed
            // for it.
            'provider' => $response->meta->provider ?? $running['run']->provider,
            'model' => $response->meta->model ?? $running['run']->model,
            'output' => $this->storable($response instanceof StructuredAgentResponse
                ? ['structured' => $response->structured]
                : ['text' => $response->text]),
            // Cached tokens still crossed the wire, and the SDK's input count
            // already includes them; reasoning tokens are in the output count.
            'tokens_in' => $response->usage->inputTokens,
            'tokens_out' => $response->usage->outputTokens,
            'duration_ms' => $this->elapsed($running['startedAt']),
        ]);

        // Only ever reached on success: a thrown call never billed,
        // which is the whole of how "aborted by our error is not
        // billed" is kept without a second code path for it.
        app(SpendGuardInterface::class)->charge($agent->project, $agent::slug(), $running['run']->id);
    }

    public function failed(AgentFailed $event): void
    {
        $running = $this->pull($event->invocationId);

        if ($running === null) {
            return;
        }

        $running['run']->update([
            'status' => AgentRunStatus::Failed,
            'duration_ms' => $this->elapsed($running['startedAt']),
            'error' => $event->exception->getMessage(),
        ]);
    }

    /**
     * @return array{run: AgentRun, startedAt: float}|null
     */
    private function pull(string $invocationId): ?array
    {
        $running = $this->running[$invocationId] ?? null;

        unset($this->running[$invocationId]);

        return $running;
    }

    private function elapsed(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    /**
     * Postgres JSONB rejects a NUL byte outright, and a model answer can
     * carry one: a provider was seen returning it mid-word in place of an
     * accented character. `PageFetcher::storable()` strips the same byte
     * from crawled pages for the same reason.
     *
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function storable(array $value): array
    {
        return array_map(
            fn ($item) => match (true) {
                is_string($item) => str_replace("\0", '', $item),
                is_array($item) => $this->storable($item),
                default => $item,
            },
            $value,
        );
    }
}
