<?php

use App\Ai\Agents\WebsiteAnalyst;
use App\Ai\AgentSettings;
use App\Models\Project;
use App\Support\Settings;
use Laravel\Ai\Enums\Lab;

/**
 * The mapping lives in the database and nowhere else — defaults are written by
 * a migration, not mirrored in a config file, so there is one place to look and
 * no merge to reason about on read.
 */
function mapping(string $agent = 'website-analyst'): array
{
    $agents = app(AgentSettings::class);

    return [$agents->provider($agent), $agents->model($agent)];
}
it('reads the mapping the install was seeded with', function () {
    expect(mapping())->toBe([Lab::Anthropic, 'claude-opus-5']);
});

it('falls back to a conservative default for an agent added after the install', function () {
    // `known()` discovers agents from the filesystem, so a class added after
    // the migration ran genuinely has no row. It must run cheap, not throw.
    expect(app(AgentSettings::class)->model('agent-added-yesterday'))->toBe('claude-haiku-4-5')
        ->and(app(AgentSettings::class)->timeout('agent-added-yesterday'))->toBe(120);
});

it('lets a stored mapping win over the default', function () {
    app(Settings::class)->set('agents.website-analyst', ['provider' => 'anthropic', 'model' => 'claude-sonnet-5']);

    expect(mapping())->toBe([Lab::Anthropic, 'claude-sonnet-5']);
});

it('merges a partial change into what is stored, on write', function () {
    // Changing only the model must not silently drop the timeout that keeps a
    // thinking model from dying on the 60s HTTP default. The merge used to
    // happen on read against config; it now happens here.
    app(AgentSettings::class)->save('website-analyst', ['model' => 'claude-sonnet-5']);

    expect(mapping())->toBe([Lab::Anthropic, 'claude-sonnet-5'])
        ->and(app(AgentSettings::class)->timeout('website-analyst'))->toBe(300);
});

it('ignores a stored value of the wrong shape', function () {
    app(Settings::class)->set('agents.website-analyst', 'claude-sonnet-5');

    expect(mapping())->toBe([Lab::Anthropic, 'claude-haiku-4-5']);
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

it('resets to the conservative default, not to whatever shipped', function () {
    // Restoring the seeded value would mean keeping a second copy of it in
    // code, which is the config-shadows-database duplication this removed.
    app(AgentSettings::class)->save('website-analyst', ['model' => 'claude-sonnet-5']);

    $this->artisan('eveil:agent-model', ['agent' => 'website-analyst', '--reset' => true])->assertSuccessful();

    expect(mapping())->toBe([Lab::Anthropic, 'claude-haiku-4-5']);
});

it('lists every agent with where its mapping came from', function () {
    app(Settings::class)->set('agents.company-qualifier', ['model' => 'claude-sonnet-5']);

    $this->artisan('eveil:agent-model')
        ->expectsOutputToContain('target-profile-deriver')
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
    app(Settings::class)->set('agents.website-analyst', ['provider' => 'anthropic']);

    expect(app(AgentSettings::class)->model('website-analyst'))->toBeNull();
});

it('lists every agent it finds in the code, not a hand-kept list', function () {
    // An enum would drift the day someone adds an agent and forgets the case.
    expect(app(AgentSettings::class)->known())->toBe([
        'company-qualifier',
        'contact-extractor',
        'contact-page-finder',
        'discovery-planner',
        'listing-extractor',
        'result-triage',
        'target-profile-deriver',
        'website-analyst',
    ]);
});

it('lets two thinking agents run on different models', function () {
    // Impossible under the old shared "planner" line, and a distinction worth
    // having: target profile derivation runs once per project, search planning runs often.
    app(Settings::class)->set('agents.discovery-planner', ['model' => 'claude-sonnet-5']);

    expect(mapping('target-profile-deriver')[1])->toBe('claude-opus-5')
        ->and(mapping('discovery-planner')[1])->toBe('claude-sonnet-5');
});
