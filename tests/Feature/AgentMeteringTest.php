<?php

use App\Ai\Agents\WebsiteAnalyst;
use App\Enums\AgentRunStatus;
use App\Models\AgentRun;
use App\Models\Project;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredTextResponse;

/**
 * No agent call goes unmetered. Metering rides on agent middleware, so it
 * applies to every agent without a call site remembering.
 *
 * Tokens, never money: no provider reports a price, so a cost column would be
 * our own multiplication against a list price that drifts — wrong quietly, in a
 * field that looks authoritative. Self-hosted users pay their provider and want
 * tokens; cloud users are billed in credits, which the operator calibrates from
 * these counts against a real invoice.
 */
function analyst(): WebsiteAnalyst
{
    return new WebsiteAnalyst(Project::factory()->create());
}

it('records tokens, cost and duration for a successful call', function () {
    WebsiteAnalyst::fake([
        new StructuredTextResponse(
            ['what_it_does' => 'Widgets.'],
            '{"what_it_does":"Widgets."}',
            new Usage(promptTokens: 20_000, completionTokens: 1_000),
            new Meta('anthropic', 'claude-opus-5'),
        ),
    ]);

    analyst()->prompt('Analyse this.');

    $run = AgentRun::sole();

    expect($run->status)->toBe(AgentRunStatus::Succeeded)
        // The slug, not a category: the meter has to join a credit grid that
        // bills per action.
        ->and($run->agent)->toBe('website-analyst')
        ->and($run->tokens_in)->toBe(20_000)
        ->and($run->tokens_out)->toBe(1_000);
});

it('counts cached tokens as input so the meter matches what was sent', function () {
    WebsiteAnalyst::fake([
        new StructuredTextResponse(
            ['what_it_does' => 'Widgets.'],
            '{}',
            new Usage(promptTokens: 100, cacheReadInputTokens: 200, cacheWriteInputTokens: 50),
            new Meta('anthropic', 'claude-opus-5'),
        ),
    ]);

    analyst()->prompt('Analyse this.');

    expect(AgentRun::sole()->tokens_in)->toBe(350);
});

it('records a failed run and rethrows', function () {
    WebsiteAnalyst::fake(fn () => throw new RuntimeException('provider exploded'));

    // Middleware rather than an event listener precisely so a throwing provider
    // is recorded as failed instead of leaving a row stuck on "running".
    expect(fn () => analyst()->prompt('Analyse this.'))
        ->toThrow(RuntimeException::class, 'provider exploded');

    $run = AgentRun::sole();

    expect($run->status)->toBe(AgentRunStatus::Failed)
        ->and($run->error)->toContain('provider exploded');
});

it('attaches the run to the project the agent acts for', function () {
    $project = Project::factory()->create();
    WebsiteAnalyst::fake([['what_it_does' => 'Widgets.']]);

    (new WebsiteAnalyst($project))->prompt('Analyse this.');

    expect(AgentRun::sole()->project_id)->toBe($project->id);
});
