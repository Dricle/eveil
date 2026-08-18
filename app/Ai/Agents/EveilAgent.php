<?php

namespace App\Ai\Agents;

use App\Ai\AgentSettings;
use App\Ai\Middleware\RecordsAgentRun;
use App\Ai\ProviderCredentials;
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

    /**
     * Whether a weaker model BREAKS this agent rather than merely making it
     * worse. The generative agents degrade gracefully — a cheaper model writes
     * a flatter summary, and the run still means something. The ones that read
     * a page and return fields do not: a small local model returns broken
     * extractions, which look like results and are not.
     *
     * Declared on the class so the settings screen reads it from the code, the
     * same way it discovers the agents themselves.
     */
    public static function requiresStrictStructure(): bool
    {
        return false;
    }

    /**
     * The project's own instructions for anything written in its name — tone,
     * language, words to avoid. Appended by the agents that WRITE, and by them
     * only: an extractor returns fields nobody reads as prose, and telling it
     * to avoid emoji is prompt it has to spend attention on for nothing.
     *
     * Placed last and stated as overriding, because that is what the user
     * expects of a box they filled in themselves.
     */
    protected function projectInstructions(): string
    {
        $instructions = trim((string) $this->project->prompt_instructions);

        if ($instructions === '') {
            return '';
        }

        return <<<PROMPT


            The user's own instructions for how this product writes. Where they disagree
            with anything above, follow these:

            {$instructions}
            PROMPT;
    }

    public function recordInto(AgentRun $run): static
    {
        $this->run = $run;

        return $this;
    }

    public function provider(): Lab|string
    {
        // The key the provider is called with is a stored secret, and this is
        // the last moment before `laravel/ai` builds the driver from config.
        app(ProviderCredentials::class)->apply();

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
