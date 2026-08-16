<?php

namespace App\Ai\Agents;

use App\Ai\AgentSettings;
use App\Ai\Middleware\RecordsAgentRun;
use App\Models\AgentRun;
use App\Models\Project;
use Illuminate\Support\Str;
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
 * database-backed mapping needs — a model change is a settings
 * change, never a deploy.
 */
abstract class EveilAgent implements Agent, HasMiddleware
{
    use Promptable;

    /**
     * The row this call reports into. Set when the run was created before the
     * call — a job queued from a screen writes its `pending` row at dispatch,
     * so the page can say the work is coming while it is still in the queue.
     * Left null, the metering middleware opens a row of its own.
     */
    public ?AgentRun $run = null;

    public function __construct(public readonly Project $project) {}

    /**
     * Which line of the settings screen governs this agent, and what
     * `agent_runs` records — one line per agent, not per vague category, so
     * the meter joins the credit grid, which bills per action.
     *
     * Static because the slug is a property of the class: whoever opens the
     * run row names the agent without constructing one.
     */
    public static function slug(): string
    {
        return Str::kebab(class_basename(static::class));
    }

    public function recordInto(AgentRun $run): static
    {
        $this->run = $run;

        return $this;
    }

    public function provider(): Lab|string
    {
        return $this->settings()->provider(static::slug());
    }

    public function model(): ?string
    {
        return $this->settings()->model(static::slug());
    }

    public function timeout(): int
    {
        return $this->settings()->timeout(static::slug());
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
