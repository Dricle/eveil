<?php

use App\Ai\Agents\WebsiteAnalyst;
use App\Ai\AgentSettings;
use App\Models\Project;
use App\Support\Settings;
use Laravel\Ai\Enums\Lab;

/**
 * ADR-026: the mapping lives in the database so the operator can change a model
 * from the settings screen. Config only supplies the shipped default.
 */
function mapping(string $agent = 'website-analyst'): array
{
    $agents = app(AgentSettings::class);

    return [$agents->provider($agent), $agents->model($agent)];
}
it('falls back to the shipped default when nothing is stored', function () {
    expect(mapping())->toBe([Lab::Anthropic, 'claude-opus-5']);
});

it('lets a stored mapping win over the default', function () {
    app(Settings::class)->set('agents.website-analyst', ['provider' => 'anthropic', 'model' => 'claude-sonnet-5']);

    expect(mapping())->toBe([Lab::Anthropic, 'claude-sonnet-5']);
});

it('merges a partial override onto the default', function () {
    // Changing only the model must not silently drop the timeout that keeps a
    // thinking model from dying on the 60s HTTP default.
    app(Settings::class)->set('agents.website-analyst', ['model' => 'claude-sonnet-5']);

    expect(mapping())->toBe([Lab::Anthropic, 'claude-sonnet-5'])
        ->and(app(AgentSettings::class)->timeout('website-analyst'))->toBe(300);
});

it('ignores a stored value of the wrong shape', function () {
    app(Settings::class)->set('agents.website-analyst', 'claude-sonnet-5');

    expect(mapping())->toBe([Lab::Anthropic, 'claude-opus-5']);
});

it('changes the model from the command line', function () {
    $this->artisan('eveil:agent-model', ['agent' => 'website-analyst', '--model' => 'claude-sonnet-5'])
        ->expectsOutputToContain('claude-sonnet-5')
        ->assertSuccessful();

    expect(mapping())->toBe([Lab::Anthropic, 'claude-sonnet-5']);
});

it('warns that the credit grid is calibrated on the model mix', function () {
    $this->artisan('eveil:agent-model', ['agent' => 'website-analyst', '--model' => 'claude-sonnet-5'])
        ->expectsOutputToContain('credit_prices')
        ->assertSuccessful();
});

it('resets back to the shipped default', function () {
    app(Settings::class)->set('agents.website-analyst', ['model' => 'claude-sonnet-5']);

    $this->artisan('eveil:agent-model', ['agent' => 'website-analyst', '--reset' => true])->assertSuccessful();

    expect(mapping())->toBe([Lab::Anthropic, 'claude-opus-5']);
});

it('lists every agent with where its mapping came from', function () {
    app(Settings::class)->set('agents.company-qualifier', ['model' => 'claude-sonnet-5']);

    $this->artisan('eveil:agent-model')
        ->expectsOutputToContain('icp-deriver')
        ->expectsOutputToContain('database')
        ->assertSuccessful();
});

it('rejects an agent that does not exist', function () {
    $this->artisan('eveil:agent-model', ['agent' => 'nope'])
        ->expectsOutputToContain('Unknown agent')
        ->assertFailed();
});

it('sees a change made in another process', function () {
    mapping();

    // Settings are cached forever because they are read on every agent call;
    // a write from the settings screen has to invalidate that cache or the
    // change appears to do nothing until the next deploy.
    app(Settings::class)->set('agents.website-analyst', ['model' => 'claude-sonnet-5']);

    expect(mapping()[1])->toBe('claude-sonnet-5');
});

it('feeds the mapping straight into the agent laravel/ai asks', function () {
    app(Settings::class)->set('agents.website-analyst', ['model' => 'claude-sonnet-5', 'timeout' => 200]);

    // `Promptable` consults these before its own attributes, which is what
    // makes a model change a settings change rather than a deploy.
    $agent = new WebsiteAnalyst(Project::factory()->create());

    expect($agent->model())->toBe('claude-sonnet-5')
        // A Lab case, not a string: it is the package's own type and the one
        // `Promptable` expects back.
        ->and($agent->provider())->toBe(Lab::Anthropic)
        ->and($agent->timeout())->toBe(200);
});

it('keeps an unknown provider as a plain string', function () {
    // An OpenAI-compatible endpoint is referenced by its config key, which no
    // enum case can cover.
    app(Settings::class)->set('agents.website-analyst', ['provider' => 'my-local-llm']);

    expect(app(AgentSettings::class)->provider('website-analyst'))->toBe('my-local-llm')
        ->and(app(AgentSettings::class)->providerName('website-analyst'))->toBe('my-local-llm');
});

it('leaves the model unset so the provider default applies', function () {
    config()->set('eveil.agents.website-analyst', ['provider' => Lab::Anthropic]);

    expect(app(AgentSettings::class)->model('website-analyst'))->toBeNull();
});

it('lists every agent it finds in the code, not a hand-kept list', function () {
    // An enum would drift the day someone adds an agent and forgets the case.
    expect(app(AgentSettings::class)->known())->toBe([
        'company-qualifier',
        'contact-extractor',
        'discovery-planner',
        'icp-deriver',
        'listing-extractor',
        'website-analyst',
    ]);
});

it('lets two thinking agents run on different models', function () {
    // Impossible under the old shared "planner" line, and a distinction worth
    // having: ICP derivation runs once per project, search planning runs often.
    app(Settings::class)->set('agents.discovery-planner', ['model' => 'claude-sonnet-5']);

    expect(mapping('icp-deriver')[1])->toBe('claude-opus-5')
        ->and(mapping('discovery-planner')[1])->toBe('claude-sonnet-5');
});
