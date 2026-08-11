<?php

namespace App\Ai;

use App\Enums\AgentType;
use App\Support\Settings;

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

    public function provider(AgentType $type): string
    {
        return (string) ($this->for($type)['provider'] ?? 'anthropic');
    }

    public function model(AgentType $type): string
    {
        return (string) ($this->for($type)['model'] ?? 'claude-haiku-4-5');
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
     * @return array{provider?: string, model?: string, timeout?: int}
     */
    public function for(AgentType $type): array
    {
        /** @var array{provider?: string, model?: string, timeout?: int} $default */
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
     * @param  array{provider?: string, model?: string, timeout?: int}  $values
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
