<?php

namespace App\Ai\Agents;

use App\Ai\AgentSettings;
use App\Ai\Middleware\RecordsAgentRun;
use App\Enums\AgentType;
use App\Models\Project;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

/**
 * What every Eveil agent shares: the project it acts for, where its model comes
 * from, and the fact that its call is metered.
 *
 * `Promptable` looks for `provider()`, `model()` and `timeout()` on the agent
 * before falling back to its own attributes, which is exactly the hook the
 * database-backed mapping needs (ADR-026) — a model change is a settings
 * change, never a deploy.
 */
abstract class EveilAgent implements Agent, HasMiddleware
{
    use Promptable;

    public function __construct(public readonly Project $project) {}

    /**
     * Which line of the settings screen governs this agent.
     */
    abstract public function type(): AgentType;

    public function provider(): Lab|string
    {
        return $this->settings()->provider($this->type());
    }

    public function model(): ?string
    {
        return $this->settings()->model($this->type());
    }

    public function timeout(): int
    {
        return $this->settings()->timeout($this->type());
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [app(RecordsAgentRun::class)];
    }

    private function settings(): AgentSettings
    {
        return app(AgentSettings::class);
    }
}
