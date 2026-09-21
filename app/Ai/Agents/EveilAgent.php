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
 * database-backed mapping needs: a model change is a settings
 * change, never a deploy.
 */
abstract class EveilAgent implements Agent, HasMiddleware
{
    use Promptable;

    /**
     * The row this call reports into. Set when the run was created before the
     * call. A job queued from a screen writes its `pending` row at dispatch,
     * so the page can say the work is coming while it is still in the queue.
     * Left null, the metering middleware opens a row of its own.
     */
    public ?AgentRun $run = null;

    public function __construct(public readonly Project $project) {}

    /**
     * Which line of the settings screen governs this agent, and what
     * `agent_runs` records: one line per agent, not per vague category, so
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
     * worse. The generative agents degrade gracefully, since a cheaper model writes
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
     * Whether this agent shipped calibrated to run on the cheap model rather
     * than the generative one, per `seed_default_settings` /
     * `seed_repo_explorer_agent_settings`. False by default: an agent nobody
     * has measured this for is assumed to need the full model, not assumed
     * safe to downgrade.
     *
     * Purely informational, read by the settings screen as a badge. It does
     * not change what an agent runs on: `AgentSettings` still owns that.
     */
    public static function smallModelSufficient(): bool
    {
        return false;
    }

    /**
     * The project's own instructions for the EMAILS written in its name: tone,
     * language, words to avoid. The "How the AI writes" box on Settings ->
     * Project. Appended by the agents that write an email a lead actually
     * reads - `MessagePersonalizer`, `SequenceWriter`, `VariantWriter` - and
     * by them only. Not appended to agents whose output is raw material for
     * those (`CompanyQualifier`'s fit_reason, `WebsiteAnalyst`'s portrait,
     * `TargetProfileDeriver`'s profiles): the actual email writer re-applies
     * style when it turns that material into prose, so requiring compliance
     * one step upstream too would be redundant, and requiring it of
     * extractors that return fields nobody reads as prose spends the model's
     * attention for nothing. Not appended to `Evie` either - see
     * `Evie::emailPreferencesForReference()` for why the chat needs to know
     * about this box without being governed by it. Not appended to
     * `LinkedinPostWriter` - a public feed post has its own box,
     * `linkedinInstructions()` below, since it is a different kind of writing
     * with its own audience and does not default to the email tone.
     *
     * Placed last and stated as overriding, because that is what the user
     * expects of a box they filled in themselves.
     */
    protected function emailWritingInstructions(): string
    {
        $instructions = trim((string) $this->project->prompt_instructions);

        if ($instructions === '') {
            return '';
        }

        return <<<PROMPT


            The user's own instructions for how this product writes emails. Where they
            disagree with anything above, follow these:

            {$instructions}
            PROMPT;
    }

    /**
     * LinkedIn's own tone, independent of `emailWritingInstructions()` above:
     * a public feed post under the user's own name is a different kind of
     * writing than a cold email, with its own audience, so it gets its own
     * box rather than inheriting the email one by default. Only
     * `LinkedinPostWriter` calls this.
     */
    protected function linkedinInstructions(): string
    {
        $instructions = trim((string) $this->project->linkedin_prompt_instructions);

        if ($instructions === '') {
            return '';
        }

        return <<<PROMPT


            The user's own instructions for how this product writes LinkedIn posts.
            Where they disagree with anything above, follow these:

            {$instructions}
            PROMPT;
    }

    /**
     * The full product portrait, the same fields and order as the
     * Knowledge Base settings screen (`resources/js/pages/settings/KnowledgeBase.vue`):
     * what it does, who it is for, value proposition, positioning, pricing
     * model, key features, competitors, proof points. `what_it_does` alone
     * is not enough context for a judgment or a piece of writing about the
     * product - a reader who only sees that field is missing exactly the
     * fields (positioning, proof points, who buys it) that make a product
     * decision or a piece of copy actually specific rather than generic.
     */
    protected function productPortrait(): string
    {
        $knowledgeBase = $this->project->knowledge_base ?? [];

        if (empty($knowledgeBase)) {
            return 'Not analyzed yet.';
        }

        $lines = [];

        foreach ([
            'what_it_does' => 'What it does',
            'who_it_is_for' => 'Who it is for',
            'value_proposition' => 'Value proposition',
            'positioning' => 'Positioning',
            'pricing_model' => 'Pricing model',
        ] as $key => $label) {
            if (! empty($knowledgeBase[$key])) {
                $lines[] = "{$label}: {$knowledgeBase[$key]}";
            }
        }

        foreach ([
            'key_features' => 'Key features',
            'competitors' => 'Competitors',
            'proof_points' => 'Proof points',
        ] as $key => $label) {
            if (! empty($knowledgeBase[$key]) && is_array($knowledgeBase[$key])) {
                $lines[] = "{$label}: ".implode(', ', $knowledgeBase[$key]);
            }
        }

        return $lines === [] ? 'Not analyzed yet.' : implode("\n", $lines);
    }

    public function recordInto(AgentRun $run): static
    {
        $this->run = $run;

        return $this;
    }

    /**
     * The configured provider, plus a failover entry when another provider
     * has a key stored: `Promptable` only retries on a `FailoverableException`
     * (rate limit, overload, insufficient credits, and connection failures
     * like the OpenAI timeout that prompted this), so a dead provider with no
     * fallback configured still throws exactly as before.
     *
     * The fallback runs on ITS default model, never the primary's configured
     * one - a model id belongs to the provider that publishes it. Which
     * provider gets picked is whichever configured one isn't the primary;
     * there is no priority order beyond that.
     */
    public function provider(): Lab|array|string
    {
        // The key the provider is called with is a stored secret, and this is
        // the last moment before `laravel/ai` builds the driver from config.
        app(ProviderCredentials::class)->apply();

        $primary = $this->settings()->provider(static::slug());
        $fallback = $this->fallbackProvider($primary);

        if ($fallback === null) {
            return $primary;
        }

        return [
            ($primary instanceof Lab ? $primary->value : $primary) => $this->model(),
            $fallback->value => null,
        ];
    }

    private function fallbackProvider(Lab|string $primary): ?Lab
    {
        $primary = $primary instanceof Lab ? $primary->value : $primary;
        $credentials = app(ProviderCredentials::class);

        foreach (Lab::cases() as $lab) {
            if ($lab->value !== $primary && $credentials->isConfigured($lab->value)) {
                return $lab;
            }
        }

        return null;
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
