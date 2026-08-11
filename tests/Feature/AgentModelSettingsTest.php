<?php

use App\Ai\AgentRunner;
use App\Enums\AgentType;
use App\Support\Settings;

/**
 * ADR-026: the mapping lives in the database so the operator can change a model
 * from the settings screen. Config only supplies the shipped default.
 */
it('falls back to the shipped default when nothing is stored', function () {
    expect(app(AgentRunner::class)->resolve(AgentType::Planner))->toBe(['anthropic', 'claude-opus-5']);
});

it('lets a stored mapping win over the default', function () {
    app(Settings::class)->set('agents.planner', ['provider' => 'anthropic', 'model' => 'claude-sonnet-5']);

    expect(app(AgentRunner::class)->resolve(AgentType::Planner))->toBe(['anthropic', 'claude-sonnet-5']);
});

it('merges a partial override onto the default', function () {
    // Changing only the model must not silently drop the timeout that keeps a
    // thinking model from dying on the 60s HTTP default.
    app(Settings::class)->set('agents.planner', ['model' => 'claude-sonnet-5']);

    expect(app(AgentRunner::class)->resolve(AgentType::Planner))->toBe(['anthropic', 'claude-sonnet-5'])
        ->and(app(AgentRunner::class)->timeout(AgentType::Planner))->toBe(300);
});

it('ignores a stored value of the wrong shape', function () {
    app(Settings::class)->set('agents.planner', 'claude-sonnet-5');

    expect(app(AgentRunner::class)->resolve(AgentType::Planner))->toBe(['anthropic', 'claude-opus-5']);
});

it('changes the model from the command line', function () {
    $this->artisan('eveil:agent-model', ['agent' => 'planner', '--model' => 'claude-sonnet-5'])
        ->expectsOutputToContain('claude-sonnet-5')
        ->assertSuccessful();

    expect(app(AgentRunner::class)->resolve(AgentType::Planner))->toBe(['anthropic', 'claude-sonnet-5']);
});

it('warns that the credit grid is calibrated on the model mix', function () {
    $this->artisan('eveil:agent-model', ['agent' => 'planner', '--model' => 'claude-sonnet-5'])
        ->expectsOutputToContain('credit_prices')
        ->assertSuccessful();
});

it('resets back to the shipped default', function () {
    app(Settings::class)->set('agents.planner', ['model' => 'claude-sonnet-5']);

    $this->artisan('eveil:agent-model', ['agent' => 'planner', '--reset' => true])->assertSuccessful();

    expect(app(AgentRunner::class)->resolve(AgentType::Planner))->toBe(['anthropic', 'claude-opus-5']);
});

it('lists every agent with where its mapping came from', function () {
    app(Settings::class)->set('agents.qualifier', ['model' => 'claude-sonnet-5']);

    $this->artisan('eveil:agent-model')
        ->expectsOutputToContain('planner')
        ->expectsOutputToContain('database')
        ->assertSuccessful();
});

it('rejects an agent that does not exist', function () {
    $this->artisan('eveil:agent-model', ['agent' => 'nope'])
        ->expectsOutputToContain('Unknown agent')
        ->assertFailed();
});

it('sees a change made in another process', function () {
    app(AgentRunner::class)->resolve(AgentType::Planner);

    // Settings are cached forever because they are read on every agent call;
    // a write from the settings screen has to invalidate that cache or the
    // change appears to do nothing until the next deploy.
    app(Settings::class)->set('agents.planner', ['model' => 'claude-sonnet-5']);

    expect(app(AgentRunner::class)->resolve(AgentType::Planner)[1])->toBe('claude-sonnet-5');
});
