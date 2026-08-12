<?php

namespace App\Ai;

use App\Enums\AgentType;
use App\Support\Settings;
use Laravel\Ai\Enums\Lab;

/**
 * Where each agent's provider, model and timeout come from (ADR-026).
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
    public function provider(AgentType $type): Lab|string
    {
        $provider = $this->for($type)['provider'] ?? Lab::Anthropic;

        if ($provider instanceof Lab) {
            return $provider;
        }

        return Lab::tryFrom((string) $provider) ?? (string) $provider;
    }

    /**
     * The provider as a config key — `config('ai.providers.<name>')` and any
     * display want this, not the enum.
     */
    public function providerName(AgentType $type): string
    {
        $provider = $this->provider($type);

        return $provider instanceof Lab ? $provider->value : $provider;
    }

    /**
     * Null on purpose when nothing is configured: `laravel/ai` then resolves
     * the provider's own default model, which beats a hardcoded guess here.
     */
    public function model(AgentType $type): ?string
    {
        $model = $this->for($type)['model'] ?? null;

        return $model === null ? null : (string) $model;
    }

    /**
     * The 60-second HTTP default is not enough for a thinking model: the first
     * live ICP derivation took 69 seconds and died on it.
     */
    public function timeout(AgentType $type): int
    {
        return (int) ($this->for($type)['timeout'] ?? 120);
    }

    /**
     * @return array{provider?: Lab|string, model?: string, timeout?: int}
     */
    public function for(AgentType $type): array
    {
        /** @var array{provider?: Lab|string, model?: string, timeout?: int} $default */
        $default = config("eveil.agents.{$type->value}", []);

        // Whatever the operator saved, so the shape is checked, not trusted.
        $override = $this->settings->get("agents.{$type->value}", []);

        return is_array($override) ? array_merge($default, $override) : $default;
    }

    public function isOverridden(AgentType $type): bool
    {
        return $this->settings->get("agents.{$type->value}") !== null;
    }

    /**
     * @param  array{provider?: Lab|string, model?: string, timeout?: int}  $values
     */
    public function save(AgentType $type, array $values): void
    {
        $this->settings->set("agents.{$type->value}", array_filter($values));
    }

    public function reset(AgentType $type): void
    {
        $this->settings->forget("agents.{$type->value}");
    }
}
