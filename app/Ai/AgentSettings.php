<?php

namespace App\Ai;

use App\Support\Settings;
use Illuminate\Support\Str;
use Laravel\Ai\Enums\Lab;

/**
 * Where each agent's provider, model and timeout come from.
 *
 * Keyed on the agent slug, one line per agent. A coarser grouping was tried and
 * removed: three different jobs shared one "planner" line, so the meter could
 * not tell `project.analyze` from `target_profile.derive` — while the credit grid bills
 * them separately — and there was no way to put target profile derivation on the expensive
 * model while search planning ran on a cheaper one.
 *
 * The superadmin's choice lives in the database and wins; `config/eveil.php`
 * only supplies the shipped default so a fresh install works without opening
 * the settings screen. Agents read this through `provider()`, `model()` and
 * `timeout()` — the hooks `Promptable` consults before its own attributes.
 */
class AgentSettings
{
    public function __construct(private Settings $settings) {}

    /**
     * A `Lab` case whenever the provider is one the package knows, and a plain
     * string otherwise — an OpenAI-compatible endpoint is referenced by its
     * config key, which no enum can cover.
     */
    public function provider(string $agent): Lab|string
    {
        $provider = $this->for($agent)['provider'] ?? Lab::Anthropic;

        if ($provider instanceof Lab) {
            return $provider;
        }

        return Lab::tryFrom((string) $provider) ?? (string) $provider;
    }

    /**
     * The provider as a config key — `config('ai.providers.<name>')` and any
     * display want this, not the enum.
     */
    public function providerName(string $agent): string
    {
        $provider = $this->provider($agent);

        return $provider instanceof Lab ? $provider->value : $provider;
    }

    /**
     * Null on purpose when nothing is configured: `laravel/ai` then resolves
     * the provider's own default model, which beats a hardcoded guess here.
     */
    public function model(string $agent): ?string
    {
        $model = $this->for($agent)['model'] ?? null;

        return $model === null ? null : (string) $model;
    }

    /**
     * The 60-second HTTP default is not enough for a thinking model: the first
     * live target profile derivation took 69 seconds and died on it.
     */
    public function timeout(string $agent): int
    {
        return (int) ($this->for($agent)['timeout'] ?? 120);
    }

    /**
     * @return array{provider?: Lab|string, model?: string, timeout?: int}
     */
    public function for(string $agent): array
    {
        /** @var array{provider?: Lab|string, model?: string, timeout?: int} $default */
        $default = config("eveil.agents.{$agent}", []);

        // Whatever the operator saved, so the shape is checked, not trusted.
        $override = $this->settings->get("agents.{$agent}", []);

        return is_array($override) ? array_merge($default, $override) : $default;
    }

    public function isOverridden(string $agent): bool
    {
        return $this->settings->get("agents.{$agent}") !== null;
    }

    /**
     * @param  array{provider?: Lab|string, model?: string, timeout?: int}  $values
     */
    public function save(string $agent, array $values): void
    {
        $this->settings->set("agents.{$agent}", array_filter($values));
    }

    public function reset(string $agent): void
    {
        $this->settings->forget("agents.{$agent}");
    }

    /**
     * Every agent the settings screen should list, discovered from the code
     * rather than from a hand-kept enum that would drift the moment someone
     * adds an agent.
     *
     * @return array<int, string>
     */
    public function known(): array
    {
        return collect(glob(app_path('Ai/Agents/*.php')) ?: [])
            ->map(fn (string $path): string => 'App\\Ai\\Agents\\'.basename($path, '.php'))
            ->filter(fn (string $class): bool => is_subclass_of($class, Agents\EveilAgent::class)
                && ! (new \ReflectionClass($class))->isAbstract())
            ->map(fn (string $class): string => Str::kebab(class_basename($class)))
            ->sort()
            ->values()
            ->all();
    }
}
