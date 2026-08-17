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
 * The database is the only source: defaults are written by a migration rather
 * than mirrored in a config file, so there is one place to look and no merge to
 * reason about. Agents read this through `provider()`, `model()` and
 * `timeout()` — the hooks `Promptable` consults before its own attributes.
 */
class AgentSettings
{
    /**
     * What an agent runs on before anyone has said otherwise.
     *
     * Not a config fallback — the shipped values live in a migration. This
     * exists for one narrow case: an agent class added AFTER the install was
     * migrated has no row yet, and should run on the cheap model rather than
     * throw. `AgentSettings::known()` discovers agents from the filesystem, so
     * that gap is real and normal.
     */
    private const DEFAULT = ['provider' => 'anthropic', 'model' => 'claude-haiku-4-5', 'timeout' => 120];

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
        return (int) ($this->for($agent)['timeout'] ?? self::DEFAULT['timeout']);
    }

    /**
     * @return array{provider?: Lab|string, model?: string, timeout?: int}
     */
    public function for(string $agent): array
    {
        // Whatever the operator saved, so the shape is checked, not trusted.
        $stored = $this->settings->get("agents.{$agent}");

        /** @var array{provider?: Lab|string, model?: string, timeout?: int} */
        return is_array($stored) && $stored !== [] ? $stored : self::DEFAULT;
    }

    public function isOverridden(string $agent): bool
    {
        return $this->settings->get("agents.{$agent}") !== null;
    }

    /**
     * @param  array{provider?: Lab|string, model?: string, timeout?: int}  $values
     */
    /**
     * Merges into what is already stored rather than replacing it.
     *
     * @param  array{provider?: Lab|string, model?: string, timeout?: int}  $values
     *
     * Changing only the model must not silently drop the timeout: a thinking
     * model on the 60s HTTP default dies, which is how the first real profile
     * derivation was lost at 69 seconds. The merge used to happen on READ,
     * against a config file; now the stored row is the only source, so it
     * happens on write.
     */
    public function save(string $agent, array $values): void
    {
        $this->settings->set("agents.{$agent}", array_merge($this->for($agent), array_filter($values)));
    }

    /**
     * Drops the row, so the agent falls back to `self::DEFAULT`.
     *
     * Note what this does NOT do: restore whatever the install originally
     * shipped with. Those values were written by a migration and the migration
     * is not a lookup table — keeping a second copy of them in code to support
     * this one command would recreate exactly the config-shadows-database
     * duplication that was just removed. Reset lands on the conservative
     * default, the command prints what it landed on, and an operator who wants
     * something else sets it explicitly.
     */
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
        return array_keys($this->classes());
    }

    /**
     * The same discovery, keyed by slug, for whoever needs the class itself —
     * the screen asks each one whether a weak model would break it rather than
     * merely make it worse.
     *
     * @return array<string, class-string<Agents\EveilAgent>>
     */
    public function classes(): array
    {
        return collect(glob(app_path('Ai/Agents/*.php')) ?: [])
            ->map(fn (string $path): string => 'App\\Ai\\Agents\\'.basename($path, '.php'))
            ->filter(fn (string $class): bool => is_subclass_of($class, Agents\EveilAgent::class)
                && ! (new \ReflectionClass($class))->isAbstract())
            ->mapWithKeys(fn (string $class): array => [Str::kebab(class_basename($class)) => $class])
            ->sortKeys()
            ->all();
    }
}
