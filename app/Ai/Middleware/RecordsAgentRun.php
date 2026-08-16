<?php

namespace App\Ai\Middleware;

use App\Ai\Agents\EveilAgent;
use App\Enums\AgentRunStatus;
use App\Models\AgentRun;
use Closure;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Usage;
use Throwable;

/**
 * Every agent invocation lands in `agent_runs` — the debug log, the analysis
 * history and the billing meter are the same table.
 *
 * Middleware rather than an `AgentPrompted` listener because it wraps the call:
 * a provider that throws is recorded as failed instead of leaving a row stuck
 * on "running" forever.
 */
class RecordsAgentRun
{
    public function handle(AgentPrompt $prompt, Closure $next): mixed
    {
        $agent = $prompt->agent;

        if (! $agent instanceof EveilAgent) {
            return $next($prompt);
        }

        $attributes = [
            'project_id' => $agent->project->id,
            'agent' => $agent::slug(),
            'status' => AgentRunStatus::Running,
            'provider' => $prompt->provider->name(),
            'model' => $prompt->model,
            'input' => ['prompt' => $prompt->prompt],
        ];

        // A run queued from a screen already has its row, opened as `pending`
        // at dispatch so the page could report the work before a worker existed
        // to do it. Claim it rather than opening a second one — one invocation
        // is one row, and the meter is that count.
        $run = $agent->run;

        if ($run === null) {
            $run = AgentRun::create($attributes);
        } else {
            $run->update($attributes);
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

        return $response->then(function (AgentResponse $response) use ($run, $startedAt): void {
            $run->update([
                'status' => AgentRunStatus::Succeeded,
                'output' => property_exists($response, 'structured')
                    ? ['structured' => $response->structured]
                    : ['text' => $response->text],
                'tokens_in' => $this->inputTokens($response->usage),
                'tokens_out' => $response->usage->completionTokens,
                'duration_ms' => $this->elapsed($startedAt),
            ]);
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
