<?php

use App\Ai\AgentRunner;
use App\Ai\Agents\WebsiteAnalyst;
use App\Enums\AgentRunStatus;
use App\Enums\AgentType;
use App\Models\AgentRun;
use App\Models\Project;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredTextResponse;

/**
 * ADR-004: no agent call goes unmetered. `agent_runs` is the debug log, the
 * analysis history and the billing meter at once, so a missing row is a
 * silently unbilled call.
 */
it('records tokens, cost and duration for a successful call', function () {
    $project = Project::factory()->create();

    WebsiteAnalyst::fake([
        new StructuredTextResponse(
            ['what_it_does' => 'Widgets.'],
            '{"what_it_does":"Widgets."}',
            new Usage(promptTokens: 20_000, completionTokens: 1_000),
            new Meta('anthropic', 'claude-opus-5'),
        ),
    ]);

    app(AgentRunner::class)->run($project, AgentType::Planner, new WebsiteAnalyst, 'Analyse this.');

    $run = AgentRun::sole();

    expect($run->status)->toBe(AgentRunStatus::Succeeded)
        ->and($run->type)->toBe(AgentType::Planner)
        ->and($run->model)->toBe('claude-opus-5')
        ->and($run->tokens_in)->toBe(20_000)
        ->and($run->tokens_out)->toBe(1_000)
        // 20k input at $5/MTok plus 1k output at $25/MTok.
        ->and((float) $run->cost)->toBe(0.125)
        ->and($run->duration_ms)->toBeGreaterThanOrEqual(0);
});

it('prices cache reads at a tenth of the input rate', function () {
    $project = Project::factory()->create();

    WebsiteAnalyst::fake([
        new StructuredTextResponse(
            ['what_it_does' => 'Widgets.'],
            '{}',
            new Usage(promptTokens: 0, completionTokens: 0, cacheReadInputTokens: 1_000_000),
            new Meta('anthropic', 'claude-opus-5'),
        ),
    ]);

    app(AgentRunner::class)->run($project, AgentType::Planner, new WebsiteAnalyst, 'Analyse this.');

    // A full million cached input tokens: $5 at list price, $0.50 on a read.
    expect((float) AgentRun::sole()->cost)->toBe(0.5);
});

it('counts cached tokens as input so the meter matches what was sent', function () {
    $project = Project::factory()->create();

    WebsiteAnalyst::fake([
        new StructuredTextResponse(
            ['what_it_does' => 'Widgets.'],
            '{}',
            new Usage(promptTokens: 100, cacheReadInputTokens: 200, cacheWriteInputTokens: 50),
            new Meta('anthropic', 'claude-opus-5'),
        ),
    ]);

    app(AgentRunner::class)->run($project, AgentType::Planner, new WebsiteAnalyst, 'Analyse this.');

    expect(AgentRun::sole()->tokens_in)->toBe(350);
});

it('records a failed run and rethrows', function () {
    $project = Project::factory()->create();

    WebsiteAnalyst::fake(fn () => throw new RuntimeException('provider exploded'));

    expect(fn () => app(AgentRunner::class)->run($project, AgentType::Planner, new WebsiteAnalyst, 'Analyse this.'))
        ->toThrow(RuntimeException::class, 'provider exploded');

    $run = AgentRun::sole();

    expect($run->status)->toBe(AgentRunStatus::Failed)
        ->and($run->error)->toContain('provider exploded');
});

it('costs nothing rather than throwing on an unpriced model', function () {
    config()->set('eveil.agents.planner', ['provider' => 'anthropic', 'model' => 'some-new-model']);
    $project = Project::factory()->create();

    WebsiteAnalyst::fake([['what_it_does' => 'Widgets.']]);

    app(AgentRunner::class)->run($project, AgentType::Planner, new WebsiteAnalyst, 'Analyse this.');

    expect((float) AgentRun::sole()->cost)->toBe(0.0);
});

it('uses the configured model for the agent type', function () {
    expect(app(AgentRunner::class)->resolve(AgentType::Planner))->toBe(['anthropic', 'claude-opus-5'])
        ->and(app(AgentRunner::class)->resolve(AgentType::Extractor))->toBe(['anthropic', 'claude-haiku-4-5']);
});

it('gives the planner more time than the cheap agents', function () {
    // The first live ICP derivation died on the 60s HTTP default: a thinking
    // model on a hard prompt needs room, a page extractor does not.
    expect(app(AgentRunner::class)->timeout(AgentType::Planner))->toBe(300)
        ->and(app(AgentRunner::class)->timeout(AgentType::Extractor))->toBe(60);
});
