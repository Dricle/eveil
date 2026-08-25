<?php

namespace App\Ai\Middleware;

use App\Ai\Agents\EveilAgent;
use App\Ai\Contracts\SpendGuardInterface;
use App\Ai\OutOfCredit;
use App\Enums\AgentRunStatus;
use App\Models\AgentRun;
use Closure;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Usage;
use Throwable;

/**
 * Every agent invocation lands in `agent_runs`: the debug log, the analysis
 * history and the billing meter are the same table.
 *
 * Middleware rather than an `AgentPrompted` listener because it wraps the call:
 * a provider that throws is recorded as failed instead of leaving a row stuck
 * on "running" forever.
 */
class RecordsAgentRun
{
    public function __construct(private SpendGuardInterface $guard) {}

    public function handle(AgentPrompt $prompt, Closure $next): mixed
    {
        $agent = $prompt->agent;

        if (! $agent instanceof EveilAgent) {
            return $next($prompt);
        }

        $attributes = [
            'project_id' => $agent->project->id,
            'agent' => $agent::slug(),
            // The SDK's own id for this invocation, one per run and the same
            // one across every failover attempt. Nothing here joins on it: it
            // is what makes the step and tool events, which persist nowhere,
            // findable next to the row they belong to.
            'invocation_id' => $prompt->invocationId,
            'status' => AgentRunStatus::Running,
            // Who was ASKED. Failover can answer from somebody else, so this is
            // overwritten below with whoever actually did.
            'provider' => $prompt->provider->name(),
            'model' => $prompt->model,
            'input' => ['prompt' => $prompt->prompt],
        ];

        // A run queued from a screen already has its row, opened as `pending`
        // at dispatch so the page could report the work before a worker existed
        // to do it. Claim it rather than opening a second one: one invocation
        // is one row, and the meter is that count.
        $run = $agent->run;

        if ($run === null) {
            $run = AgentRun::create($attributes);
        } else {
            $run->update($attributes);
        }

        // Asked here, and only here, because this is the one place every agent
        // invocation passes through. A discovery run queues dozens of
        // qualifications and dozens of contact extractions with no screen in
        // between, so a check at the button would stop nothing.
        //
        // The row is marked before throwing rather than left alone: screens
        // poll it to know whether work is still coming, and a `pending` row
        // nobody ever finishes spins a spinner for ever.
        $refusal = $this->guard->refusal($agent->project, $agent::slug());

        if ($refusal !== null) {
            $run->update([
                'status' => AgentRunStatus::Failed,
                'duration_ms' => 0,
                'error' => $refusal,
            ]);

            throw new OutOfCredit($refusal);
        }

        $startedAt = microtime(true);

        try {
            $response = $next($prompt);
        } catch (Throwable $e) {
            $run->update([
                'status' => AgentRunStatus::Failed,
                'duration_ms' => $this->elapsed($startedAt),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $response->then(function (AgentResponse $response) use ($agent, $run, $startedAt): void {
            $run->update([
                'status' => AgentRunStatus::Succeeded,
                // The provider and model that ANSWERED, which after a failover
                // is not the one that was asked. Recording the request meant
                // the meter attributed a run to a provider that never billed
                // for it. Absent on a fake, so the request stands in.
                'provider' => $response->meta->provider ?? $run->provider,
                'model' => $response->meta->model ?? $run->model,
                'output' => property_exists($response, 'structured')
                    ? ['structured' => $response->structured]
                    : ['text' => $response->text],
                'tokens_in' => $this->inputTokens($response->usage),
                'tokens_out' => $response->usage->completionTokens,
                'duration_ms' => $this->elapsed($startedAt),
            ]);

            // Only ever reached on success: a thrown call never billed,
            // which is the whole of how "aborted by our error is not
            // billed" is kept without a second code path for it.
            $this->guard->charge($agent->project, $agent::slug(), $run->id);
        });
    }

    /**
     * Cached tokens still crossed the wire, so the meter counts them as input.
     */
    private function inputTokens(Usage $usage): int
    {
        return $usage->promptTokens + $usage->cacheReadInputTokens + $usage->cacheWriteInputTokens;
    }

    private function elapsed(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
