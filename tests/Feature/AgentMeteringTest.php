<?php

use App\Ai\Agents\WebsiteAnalyst;
use App\Ai\ModelPricing;
use App\Enums\AgentRunStatus;
use App\Models\AgentRun;
use App\Models\Project;
use App\Support\Settings;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredTextResponse;

/**
 * No agent call goes unmetered. Metering rides on agent middleware, so
 * it applies to every agent without a call site remembering.
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
        ->and($run->tokens_out)->toBe(1_000)
        // 20k input at $5/MTok plus 1k output at $25/MTok.
        ->and((float) $run->cost)->toBe(0.125);
});

it('prices cache reads at a tenth of the input rate', function () {
    WebsiteAnalyst::fake([
        new StructuredTextResponse(
            ['what_it_does' => 'Widgets.'],
            '{}',
            new Usage(cacheReadInputTokens: 1_000_000),
            new Meta('anthropic', 'claude-opus-5'),
        ),
    ]);

    analyst()->prompt('Analyse this.');

    // A full million cached input tokens: $5 at list price, $0.50 on a read.
    expect((float) AgentRun::sole()->cost)->toBe(0.5);
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

it('costs nothing rather than throwing on an unpriced model', function () {
    // Pricing follows the model we ASKED for, so an unpriced model has to be
    // the one configured — not merely the one the provider echoed back.
    app(Settings::class)->set('agents.website-analyst', ['model' => 'some-new-model']);

    WebsiteAnalyst::fake([
        new StructuredTextResponse(
            ['what_it_does' => 'Widgets.'],
            '{}',
            new Usage(promptTokens: 1_000),
            new Meta('anthropic', 'some-new-model'),
        ),
    ]);

    analyst()->prompt('Analyse this.');

    expect((float) AgentRun::sole()->cost)->toBe(0.0);
});

it('attaches the run to the project the agent acts for', function () {
    $project = Project::factory()->create();
    WebsiteAnalyst::fake([['what_it_does' => 'Widgets.']]);

    (new WebsiteAnalyst($project))->prompt('Analyse this.');

    expect(AgentRun::sole()->project_id)->toBe($project->id);
});

it('still prices a call when the provider answers with a dated model id', function () {
    WebsiteAnalyst::fake([
        new StructuredTextResponse(
            ['what_it_does' => 'Widgets.'],
            '{}',
            new Usage(promptTokens: 1_000_000),
            // Anthropic answers `claude-opus-5-20260115` to a `claude-opus-5`
            // request. A live run was silently metered at zero because of it,
            // while the fakes here all used the exact id.
            new Meta('anthropic', 'claude-opus-5-20260115'),
        ),
    ]);

    analyst()->prompt('Analyse this.');

    expect((float) AgentRun::sole()->cost)->toBe(5.0);
});

it('prices an unknown model at zero rather than guessing a prefix', function () {
    expect(app(ModelPricing::class)->costOf('gpt-9', new Usage(promptTokens: 1_000_000)))->toBe(0.0);
});
