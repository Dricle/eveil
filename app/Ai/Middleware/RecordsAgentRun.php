<?php

namespace App\Ai\Middleware;

use App\Ai\Agents\EveilAgent;
use App\Ai\ModelPricing;
use App\Enums\AgentRunStatus;
use App\Models\AgentRun;
use Closure;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Throwable;

/**
 * Every agent invocation lands in `agent_runs` — the debug log, the analysis
 * history and the billing meter are the same table (ADR-004).
 *
 * Middleware rather than an `AgentPrompted` listener because it wraps the call:
 * a provider that throws is recorded as failed instead of leaving a row stuck
 * on "running" forever.
 */
class RecordsAgentRun
{
    public function __construct(private ModelPricing $pricing) {}

    public function handle(AgentPrompt $prompt, Closure $next): mixed
    {
        $agent = $prompt->agent;

        if (! $agent instanceof EveilAgent) {
            return $next($prompt);
        }

        $run = AgentRun::create([
            'project_id' => $agent->project->id,
            'agent' => $agent->slug(),
            'status' => AgentRunStatus::Running,
            'provider' => $prompt->provider->name(),
            'model' => $prompt->model,
            'input' => ['prompt' => $prompt->prompt],
        ]);

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

        return $response->then(function (AgentResponse $response) use ($run, $startedAt, $prompt): void {
            $run->update([
                'status' => AgentRunStatus::Succeeded,
                'output' => property_exists($response, 'structured')
                    ? ['structured' => $response->structured]
                    : ['text' => $response->text],
                'tokens_in' => $this->pricing->inputTokens($response->usage),
                'tokens_out' => $response->usage->completionTokens,
                // Price on what we asked for, not on what came back: the provider
                // answers with a dated id that no pricing key matches.
                'cost' => $this->pricing->costOf($prompt->model, $response->usage),
                'duration_ms' => $this->elapsed($startedAt),
            ]);
        });
    }

    private function elapsed(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
