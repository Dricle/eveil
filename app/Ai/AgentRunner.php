<?php

namespace App\Ai;

use App\Enums\AgentRunStatus;
use App\Enums\AgentType;
use App\Models\AgentRun;
use App\Models\Project;
use App\Support\Settings;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Responses\AgentResponse;
use Throwable;

/**
 * The single place this application calls `laravel/ai`.
 *
 * Two reasons it exists (ADR-004): the package is pinned pre-1.0 and breaks
 * between minor versions, so an upgrade should touch one file; and every
 * invocation must land in `agent_runs` — the debug log, the analysis history
 * and the billing meter are the same table.
 */
class AgentRunner
{
    public function __construct(private Settings $settings) {}

    public function run(Project $project, AgentType $type, Agent $agent, string $prompt): AgentResponse
    {
        [$provider, $model] = $this->resolve($type);
        $timeout = $this->timeout($type);

        $run = AgentRun::create([
            'project_id' => $project->id,
            'type' => $type,
            'status' => AgentRunStatus::Running,
            'provider' => $provider,
            'model' => $model,
            'input' => ['prompt' => $prompt],
        ]);

        $startedAt = microtime(true);

        try {
            /** @var AgentResponse $response */
            $response = $agent->prompt($prompt, provider: $provider, model: $model, timeout: $timeout);
        } catch (Throwable $e) {
            $run->update([
                'status' => AgentRunStatus::Failed,
                'duration_ms' => $this->elapsed($startedAt),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $run->update([
            'status' => AgentRunStatus::Succeeded,
            'output' => $this->output($response),
            'tokens_in' => $response->usage->promptTokens + $response->usage->cacheReadInputTokens + $response->usage->cacheWriteInputTokens,
            'tokens_out' => $response->usage->completionTokens,
            'cost' => $this->cost($model, $response),
            'duration_ms' => $this->elapsed($startedAt),
        ]);

        return $response;
    }

    /**
     * Provider and model for one agent type (ADR-026).
     *
     * The superadmin's choice lives in the database and wins; the config file
     * only supplies the shipped default so a fresh install works without
     * opening the settings screen. Every call site goes through here, which is
     * why switching a model is a settings change rather than a deploy.
     *
     * @return array{0: string, 1: string}
     */
    public function resolve(AgentType $type): array
    {
        $configured = $this->settingsFor($type);

        return [
            $configured['provider'] ?? 'anthropic',
            $configured['model'] ?? 'claude-haiku-4-5',
        ];
    }

    /**
     * @return array{provider?: string, model?: string, timeout?: int}
     */
    private function settingsFor(AgentType $type): array
    {
        /** @var array{provider?: string, model?: string, timeout?: int} $default */
        $default = config("eveil.agents.{$type->value}", []);

        // Settings hold whatever the operator saved, so the shape is checked
        // rather than trusted.
        $override = $this->settings->get("agents.{$type->value}", []);

        return is_array($override) ? array_merge($default, $override) : $default;
    }

    /**
     * Seconds before the provider call is abandoned. The default of 60 is not
     * enough for a thinking model on a hard prompt — the first live ICP
     * derivation hit it.
     */
    public function timeout(AgentType $type): int
    {
        return (int) ($this->settingsFor($type)['timeout'] ?? 120);
    }

    /**
     * List-price estimate in US dollars. Cache reads bill at a tenth of the
     * input rate, cache writes at 1.25x.
     *
     * An unknown model costs 0 rather than throwing: a missing price should
     * never break a run, and the zero is visible in `agent_runs`.
     */
    private function cost(string $model, AgentResponse $response): float
    {
        /** @var array{input: float, output: float}|null $rates */
        $rates = config("eveil.pricing.{$model}");

        if ($rates === null) {
            return 0.0;
        }

        $usage = $response->usage;

        return round(
            ($usage->promptTokens * $rates['input']
                + $usage->cacheReadInputTokens * $rates['input'] * 0.1
                + $usage->cacheWriteInputTokens * $rates['input'] * 1.25
                + $usage->completionTokens * $rates['output']
            ) / 1_000_000,
            6,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function output(AgentResponse $response): array
    {
        return property_exists($response, 'structured')
            ? ['structured' => $response->structured]
            : ['text' => $response->text];
    }

    private function elapsed(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
