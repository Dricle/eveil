<?php

use App\Ai\Agents\WebsiteAnalyst;
use App\Ai\Contracts\SpendGuardInterface;
use App\Ai\OutOfCredit;
use App\Ai\UnmeteredSpend;
use App\Enums\AgentRunStatus;
use App\Models\AgentRun;
use App\Models\Project;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredTextResponse;
use RuntimeException;

/**
 * No agent call goes unmetered. Metering rides on agent middleware, so it
 * applies to every agent without a call site remembering.
 *
 * Tokens, never money: no provider reports a price, so a cost column would be
 * our own multiplication against a list price that drifts. Wrong quietly, in a
 * field that looks authoritative. Self-hosted users pay their provider and want
 * tokens; cloud users are billed in credits, which the operator calibrates from
 * these counts against a real invoice.
 */
function analyst(): WebsiteAnalyst
{
    return new WebsiteAnalyst(Project::factory()->create());
}

// One test below rebinds this to prove the refusal path; the binding is a
// GLOBAL container override that outlives that test unless put back, failing
// every other file's agent calls with "no credits left".
afterEach(function () {
    app()->bind(SpendGuardInterface::class, UnmeteredSpend::class);
});

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

it('never calls the provider when the guard refuses, and says why on the row', function () {
    // What cloud will bind: a wallet with nothing in it. Self-hosted binds the
    // opposite and this whole path never fires.
    app()->bind(SpendGuardInterface::class, fn (): SpendGuardInterface => new class implements SpendGuardInterface
    {
        public function refusal(Project $project, string $agent): ?string
        {
            return 'This project has no credits left. Top up to keep the searches running.';
        }

        public function charge(Project $project, string $agent, int $agentRunId): void {}
    });

    // Blows up if it is ever reached, which is the proof that matters: the
    // point of the guard is not spending, so being refused after the call would
    // be worth nothing.
    WebsiteAnalyst::fake(fn () => throw new RuntimeException('the provider was called'));

    expect(fn () => analyst()->prompt('Read this site'))
        ->toThrow(OutOfCredit::class);

    $run = AgentRun::query()->sole();

    // The row is marked rather than left pending: screens poll it to know
    // whether work is still coming, and a row nobody finishes spins for ever.
    expect($run->status)->toBe(AgentRunStatus::Failed)
        ->and($run->error)->toContain('no credits left')
        ->and($run->tokens_in)->toBe(0)
        ->and($run->tokens_out)->toBe(0);
});

it('spends freely on a self-hosted instance', function () {
    // The shipped binding. An app that refused to work on a machine somebody
    // runs themselves would be lying about "no artificial limits".
    expect(app(SpendGuardInterface::class))->toBeInstanceOf(UnmeteredSpend::class)
        ->and(app(SpendGuardInterface::class)->refusal(Project::factory()->create(), 'website-analyst'))->toBeNull();
});

it('records the provider that answered, not the one that was asked', function () {
    // Failover means the run can be served by somebody other than the provider
    // the agent asked for, and the meter is read as "what did each provider
    // cost us". Recording the request attributed the tokens to a provider that
    // never billed for them.
    WebsiteAnalyst::fake([
        new StructuredTextResponse(
            ['what_it_does' => 'Widgets.'],
            '{}',
            new Usage(promptTokens: 10, completionTokens: 5),
            new Meta('gemini', 'gemini-3.7-flash'),
        ),
    ]);

    analyst()->prompt('Analyse this.');

    expect(AgentRun::sole())
        ->provider->toBe('gemini')
        ->model->toBe('gemini-3.7-flash');
});

it('keeps the SDK invocation id, which is what the step and tool events are keyed by', function () {
    WebsiteAnalyst::fake([
        new StructuredTextResponse(
            ['what_it_does' => 'Widgets.'],
            '{}',
            new Usage(promptTokens: 10, completionTokens: 5),
            new Meta('anthropic', 'claude-opus-5'),
        ),
    ]);

    analyst()->prompt('Analyse this.');

    expect(AgentRun::sole()->invocation_id)->not->toBeNull();
});
