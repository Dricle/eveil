<?php

use App\Ai\AgentSettings;
use App\Ai\ProviderCredentials;
use App\Enums\HostKind;
use App\Models\AgentRun;
use App\Models\KnownHost;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Collection;

/**
 * Instance scope: what the person who ran the install decides, and nobody else.
 * Distinct from the organization role and from project access: the whole point
 * of keeping the three apart is that no organization can ever grant this.
 */
function superAdmin(): User
{
    return User::factory()->superAdmin()->create();
}

it('keeps every app setting away from an ordinary user', function (string $route) {
    $this->actingAs(User::factory()->create())->get(route($route))->assertForbidden();
})->with([
    'app-settings.provider.edit',
    'app-settings.agents.index',
    'app-settings.limits.edit',
    'app-settings.hosts.index',
]);

it('opens app settings for whoever runs the install', function (string $route, string $component) {
    $this->actingAs(superAdmin())->get(route($route))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component($component));
})->with([
    ['app-settings.provider.edit', 'app-settings/Provider'],
    ['app-settings.agents.index', 'app-settings/Agents'],
    ['app-settings.limits.edit', 'app-settings/Limits'],
    ['app-settings.hosts.index', 'app-settings/Hosts'],
]);

it('stores a provider key encrypted and never sends it back', function () {
    $this->actingAs(superAdmin())
        ->put(route('app-settings.provider.update'), ['provider' => 'anthropic', 'key' => 'sk-secret-value'])
        ->assertRedirect(route('app-settings.provider.edit'));

    // Write-only from the screen's point of view: the row holds ciphertext, and
    // the page says a key is stored without ever quoting it.
    $stored = Setting::query()->findOrFail('ai.keys.anthropic');

    expect($stored->value)->not->toContain('sk-secret-value')
        ->and($stored->is_encrypted)->toBeTrue()
        ->and(app(Settings::class)->secret('ai.keys.anthropic'))->toBe('sk-secret-value');

    $this->actingAs(superAdmin())->get(route('app-settings.provider.edit'))
        ->assertOk()
        ->assertDontSee('sk-secret-value')
        ->assertInertia(fn ($page) => $page->where('providers.0.stored', true));
});

it('hands the stored key to laravel/ai instead of the env', function () {
    config(['ai.providers.anthropic.key' => 'from-the-env']);

    app(ProviderCredentials::class)->save('anthropic', 'from-the-database');
    app(ProviderCredentials::class)->apply();

    expect(config('ai.providers.anthropic.key'))->toBe('from-the-database');
});

it('leaves the env key in place when nothing is stored', function () {
    config(['ai.providers.anthropic.key' => 'from-the-env']);

    app(ProviderCredentials::class)->apply();

    // A container configured entirely from environment variables must keep
    // working after an upgrade that adds this screen.
    expect(config('ai.providers.anthropic.key'))->toBe('from-the-env');
});

it('removes a stored key without claiming the provider is unreachable', function () {
    app(ProviderCredentials::class)->save('anthropic', 'sk-secret-value');

    $this->actingAs(superAdmin())
        ->delete(route('app-settings.provider.destroy', 'anthropic'))
        ->assertRedirect(route('app-settings.provider.edit'));

    expect(app(ProviderCredentials::class)->isStored('anthropic'))->toBeFalse();
});

it('changes what an agent runs on, from the screen', function () {
    $this->actingAs(superAdmin())
        ->put(route('app-settings.agents.update', 'website-analyst'), [
            'provider' => 'anthropic',
            'model' => 'claude-sonnet-5',
            'timeout' => 200,
        ])
        ->assertRedirect(route('app-settings.agents.index'));

    // Settings are cached forever, so a save that does not invalidate the
    // cache appears to do nothing until the next deploy.
    expect(app(AgentSettings::class)->model('website-analyst'))->toBe('claude-sonnet-5')
        ->and(app(AgentSettings::class)->timeout('website-analyst'))->toBe(200);
});

it('resets an agent to the conservative default', function () {
    app(AgentSettings::class)->save('website-analyst', ['model' => 'claude-sonnet-5']);

    $this->actingAs(superAdmin())->delete(route('app-settings.agents.destroy', 'website-analyst'))->assertRedirect();

    expect(app(AgentSettings::class)->model('website-analyst'))->toBe('claude-haiku-4-5');
});

it('refuses an agent slug that no class answers to', function () {
    $this->actingAs(superAdmin())
        ->put(route('app-settings.agents.update', 'agent-that-never-existed'), [
            'provider' => 'anthropic',
            'model' => 'claude-sonnet-5',
            'timeout' => 60,
        ])
        ->assertNotFound();
});

it('shows what each agent has already spent', function () {
    $project = Project::factory()->create();

    AgentRun::factory()->count(2)->create([
        'project_id' => $project->id,
        'agent' => 'website-analyst',
        'tokens_in' => 100,
        'tokens_out' => 50,
    ]);

    $this->actingAs(superAdmin())->get(route('app-settings.agents.index'))
        ->assertInertia(fn ($page) => $page
            ->where('agents.10.slug', 'website-analyst')
            ->where('agents.10.calls', 2)
            ->where('agents.10.tokens_in', 200)
            ->where('agents.10.tokens_out', 100));
});

it('marks the agents a weak model would break rather than merely blunt', function () {
    $this->actingAs(superAdmin())->get(route('app-settings.agents.index'))
        ->assertInertia(fn ($page) => $page
            // Extraction returns fields; a model that cannot hold the schema
            // returns wrong ones that look like results.
            ->where('agents.0.slug', 'company-qualifier')
            ->where('agents.0.strict', true)
            ->where('agents.10.slug', 'website-analyst')
            ->where('agents.10.strict', false));
});

it('suggests the models a provider names for itself, without fixing the choice', function () {
    $this->actingAs(superAdmin())->get(route('app-settings.agents.index'))
        ->assertInertia(fn ($page) => $page->where(
            'models.anthropic',
            fn (Collection $models): bool => $models->contains('claude-haiku-4-5-20251001'),
        ));

    // And anything else still saves: no list of model ids exists to validate
    // against, so a fixed one would block the model released this morning.
    $this->actingAs(superAdmin())
        ->put(route('app-settings.agents.update', 'website-analyst'), [
            'provider' => 'anthropic',
            'model' => 'claude-model-that-ships-tomorrow',
            'timeout' => 60,
        ])
        ->assertRedirect();

    expect(app(AgentSettings::class)->model('website-analyst'))->toBe('claude-model-that-ships-tomorrow');
});

it('saves the tunable limits', function () {
    $this->actingAs(superAdmin())
        ->put(route('app-settings.limits.update'), limitPayload(['discovery_max_companies' => 12, 'crawl_delay_ms' => 900]))
        ->assertRedirect(route('app-settings.limits.edit'));

    expect(app(Settings::class)->array('discovery')['max_companies'])->toBe(12)
        ->and(app(Settings::class)->int('crawl.delay_ms'))->toBe(900)
        // The other three budgets are spent against each other inside one run,
        // so writing one must not drop the rest.
        ->and(app(Settings::class)->array('discovery')['max_queries'])->toBe(12);
});

it('refuses a politeness delay of zero', function () {
    // Zero here is not "no limit": it is a crawler hammering somebody's server
    // from an instance carrying our user agent.
    $this->actingAs(superAdmin())
        ->put(route('app-settings.limits.update'), limitPayload(['crawl_delay_ms' => 0]))
        ->assertSessionHasErrors('crawl_delay_ms');

    expect(app(Settings::class)->int('crawl.delay_ms'))->toBe(500);
});

it('locks a host verdict a human corrected', function () {
    $host = KnownHost::factory()->create(['kind' => HostKind::Other]);

    $this->actingAs(superAdmin())
        ->put(route('app-settings.hosts.update', $host), [
            'kind' => 'index',
            'reason' => 'Trade directory, one page per town.',
            'is_locked' => true,
        ])
        ->assertRedirect();

    $host->refresh();

    // Locked outranks anything a model later concludes. Otherwise the next
    // triage rewrites the correction and the screen was theatre.
    expect($host->kind)->toBe(HostKind::Index)
        ->and($host->is_locked)->toBeTrue()
        ->and($host->isAuthoritative())->toBeTrue();
});

it('filters the registry by kind', function () {
    KnownHost::factory()->create(['host' => 'directory.example', 'kind' => HostKind::Index]);
    KnownHost::factory()->create(['host' => 'company.example', 'kind' => HostKind::Entity]);

    $this->actingAs(superAdmin())->get(route('app-settings.hosts.index', ['kind' => 'index']))
        ->assertInertia(fn ($page) => $page
            ->has('hosts.data', 1)
            ->where('hosts.data.0.host', 'directory.example'));
});

/**
 * Every limit the screen posts, since the form submits the whole set at once.
 *
 * @param  array<string, int>  $overrides
 * @return array<string, int|bool>
 */
function limitPayload(array $overrides = []): array
{
    return [
        'discovery_max_companies' => 40,
        'discovery_max_qualified' => 25,
        'discovery_max_pages' => 60,
        'discovery_max_queries' => 12,
        'crawl_max_pages' => 15,
        'crawl_delay_ms' => 500,
        'crawl_cache_ttl_days' => 7,
        'contacts_max_pages' => 4,
        'verification_probe' => true,
        'verification_timeout' => 5,
        'searxng_per_query' => 20,
        'overpass_per_probe' => 60,
        'directory_max_pages' => 5,
        'directory_max_entities' => 200,
        'host_registry_ttl_days' => 180,
        'host_registry_batch' => 25,
        ...$overrides,
    ];
}

it('moves every agent onto one provider in a click, keeping each timeout and tier', function () {
    // Through the screen that owns it: the key is stored encrypted, and a row
    // written any other way fails to decrypt when the guard reads it back.
    $this->actingAs(superAdmin())
        ->put(route('app-settings.provider.update'), ['provider' => 'openai', 'key' => 'sk-test-value']);

    // The two ends of the shipped mapping: one that writes prose on the smart
    // model, one that reads a page and returns fields on the cheap one.
    $before = [
        'website-analyst' => app(AgentSettings::class)->timeout('website-analyst'),
        'contact-extractor' => app(AgentSettings::class)->timeout('contact-extractor'),
    ];

    $this->actingAs(superAdmin())
        ->put(route('app-settings.agents.provider'), ['provider' => 'openai'])
        ->assertRedirect(route('app-settings.agents.index'));

    $agents = app(AgentSettings::class);

    foreach ($agents->known() as $agent) {
        expect($agents->providerName($agent))->toBe('openai');
    }

    // The timeout is not the provider's business: a thinking model on the 60s
    // HTTP default dies, and that is true whoever serves it.
    expect($agents->timeout('website-analyst'))->toBe($before['website-analyst'])
        ->and($agents->timeout('contact-extractor'))->toBe($before['contact-extractor'])
        // And no model id from the provider we left: one that does not exist on
        // the new one is a mapping that looks configured and cannot work.
        ->and($agents->model('website-analyst'))->not->toContain('claude')
        ->and($agents->model('contact-extractor'))->not->toContain('claude');
});

it('refuses to move everything onto a provider with no key', function () {
    $before = app(AgentSettings::class)->providerName('website-analyst');

    // Saved, it would look configured on this screen and fail in a job an hour
    // later, which is the one failure this screen exists to prevent.
    $this->actingAs(superAdmin())
        ->put(route('app-settings.agents.provider'), ['provider' => 'openai'])
        ->assertStatus(422);

    expect(app(AgentSettings::class)->providerName('website-analyst'))->toBe($before);
});

it('keeps the bulk switch away from an ordinary user', function () {
    $this->actingAs(User::factory()->create())
        ->put(route('app-settings.agents.provider'), ['provider' => 'openai'])
        ->assertForbidden();
});
