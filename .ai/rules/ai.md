---
paths:
  - 'app/Ai/**'
---

# Ai

## Agents are queued jobs, not daemons — and always metered
An "agent" here = a prompt + a toolset + a queued job. Nothing long-running, no persistent process per project.

Every agent invocation writes an `agent_runs` row: project_id, agent (the slug), status, input, output, tokens_in, tokens_out, duration, error. Non-negotiable and needed from day 1 — it is simultaneously the debug log, the analysis history (Epic 4), and the billing meter (Epic 12). Retrofitting it is painful.

Every run carries a hard budget (max tokens, max pages fetched, max leads produced) and aborts when hit. An unbounded agent loop that fetches pages burns real money.

## Provider and model are configurable per agent, keyed on the agent slug
ADR-026. The superadmin picks provider + model + timeout for EACH agent class from a settings screen. `laravel/ai` supplies the provider/model list; the agent list comes from the code — `AgentSettings::known()` globs `app/Ai/Agents/*.php`, so adding an agent adds a line on the screen with nothing to register.

The key is the kebab-case class basename (`EveilAgent::slug()`): `website-analyst`, `target-profile-deriver`, `discovery-planner`, `company-qualifier`, `contact-extractor`. There is no `AgentType` enum, and no coarser role taxonomy: grouping agents by role puts unrelated jobs on one line, so the meter cannot tell `project.analyze` from `targets.derive` while the credit grid bills them apart, and target profile derivation cannot run on Opus while search planning runs cheaper.

Shipped defaults in `config/eveil.php` (a fresh install must work without opening the screen): `website-analyst`, `target-profile-deriver`, `discovery-planner` = Opus 5 at 300s; `company-qualifier`, `contact-extractor` = Haiku 4.5 at 60s.

`company-qualifier` and `contact-extractor` REQUIRE reliable structured output — mark them as such in the UI. A small local model wired to the extractor via Ollama produces BROKEN extractions, not merely worse ones. The generative agents degrade gracefully; those two do not.

This is an INSTANCE-scope setting (ADR-003), superadmin-only, like the provider key — no organization admin or member ever sees it. In cloud the only superadmin is the operator, so a customer can never change the mapping.

Operational note, not a product guard: the credit grid (ADR-019) is calibrated on this exact model mix, and switching `company-qualifier` to Opus 5 multiplies the real cost of `company.qualify` by five. If the operator changes the mapping in cloud, they adjust `credit_prices` in the same move.

Fallback: Horizon backoff and retry, NO automatic cross-provider failover. The workload is asynchronous so nobody is waiting on a screen, and failing over mid-run would score leads on two different scales invisibly. Switching provider stays a deliberate config change.

## Use laravel/ai directly — there is no wrapper
Do not add one. `laravel/ai` already provides every hook a wrapper would reinvent, and treating a mature package as fragile because of its version number costs clarity for nothing.

The three extension points that matter, none of them obvious from the docs:
- **`Promptable` calls `provider()`, `model()` and `timeout()` on the agent** before falling back to its own `#[Provider]` / `#[Model]` / `#[Timeout]` attributes (see `getProvidersAndModels()` / `getTimeout()` in the trait). That is where the database-backed mapping plugs in (ADR-026), so a model change is a settings change and not a deploy.
- **`HasMiddleware`** wraps the call, so it can record a run, catch a throwing provider, and read the response. Metering rides on it rather than on the `AgentPrompted` event, because an event listener never sees the failure and would leave rows stuck on "running".
- **`AgentPrompt` carries `readonly Agent $agent`**, which is how middleware reaches the project an agent was constructed for.

Shape: one agent class per specialisation in `app/Ai/Agents/`, all extending `EveilAgent` (constructed with the `Project`; the class name IS the settings key, via `slug()`), implementing `HasStructuredOutput`. Call them plainly — `(new WebsiteAnalyst($project))->prompt($text)`. Supporting pieces: `AgentSettings` (database over config), `ModelPricing` (cost), `Middleware\RecordsAgentRun` (the `agent_runs` row).

- Use the package's own types: `provider()` returns a `Laravel\Ai\Enums\Lab` case (falling back to a plain string only for an OpenAI-compatible endpoint, which is referenced by config key). `Lab::Anthropic` sits directly in `config/eveil.php` — `config:cache` var_exports enums fine, verified. `model()` returns null when nothing is configured so `laravel/ai` resolves the provider's own default rather than us hardcoding one.
- **Tokens, never money.** `agent_runs` stores token counts only — no `cost` column, no price table, do not add either. `laravel/ai` reports usage and no provider reports a price, so any monetary figure is our own arithmetic against a number that drifts — wrong quietly, in a column that looks authoritative. Self-hosted users pay their provider directly and want token counts; cloud users are billed in credits, which the operator calibrates from these counts against a real invoice. Dollar figures quoted below are measurements, not something the app computes.
- `tokens_in` sums prompt + cache-read + cache-write tokens, so the meter reflects what actually crossed the wire. `RecordsAgentRun` owns that sum.
- Faking in tests costs nothing: `MyAgent::fake([...])` swaps the gateway, no HTTP leaves the process, and `phpunit.xml` holds a dummy `ANTHROPIC_API_KEY` so an escapee would 401 rather than bill. Pass a `StructuredTextResponse` with a real `Usage` and `Meta` when asserting on tokens — the plain-array form yields zero usage.

## Two measured facts about timeouts and cost
- **The 60s HTTP default is not enough for a thinking model.** A real `targets.derive` runs ~69s and dies on it. Timeouts are per agent in `config('eveil.agents.<slug>.timeout')`: 300s for the generative agents, 60s for the cheap read-and-extract ones, where a long timeout would only mean a stuck job holding a worker. `EveilAgent::timeout()` returns it, and `Promptable` picks it up.
- **Output tokens are where Opus costs money**, at 25 $/MTok against 5 $ for input, and generative tasks produce more than they consume: target profile derivation returns ~4 833 output tokens for ~4 456 input. Measured runs land above the estimates for that single reason (`project.analyze` 0.15 → 0.192 $, `targets.derive` 0.06 → 0.143 $). When estimating a new action's credit cost, size the OUTPUT first — the remaining grid lines are still guesses and are probably low.

## The mapping lives in the database, config is only the default
`App\Support\Settings` reads the `settings` table, cached forever and flushed on write; `App\Ai\AgentSettings` merges the stored override onto `config('eveil.agents.*')` and is what `EveilAgent::provider()/model()/timeout()` return. Nothing else reads that config — switching a model is a settings change, never a deploy.

- A partial override merges: setting only `model` keeps the timeout, which is what stops a thinking model dying on the 60s HTTP default.
- A stored value of the wrong shape is ignored rather than trusted — the settings screen writes it, so validate on read.
- The cache is invalidated on write. Without that a change from the screen appears to do nothing until the next deploy.
- `php artisan eveil:agent-model` is the command-line half: no argument lists every agent with its provider, model, timeout, whether it came from `default` or `database`, and what it has spent so far. The superadmin settings PAGE still has to be built — it belongs with Epic 1, once auth and the UI shell exist.

## Acquisition recommendations are stateful, not a report (ADR-032)
The Website agent also proposes acquisition levers the product is missing — referral scheme, editorial content, a trade fair, an offer to sector schools. Three rules separate this from the generic playbook any LLM emits in thirty seconds:

- **Evidence or nothing.** Every recommendation cites what in the knowledge base or the crawl says it is missing. "Do content marketing" is not emitted; "your site has no blog while the three competitors you name publish weekly" is.
- **Impact/effort ranking**, like the site suggestions of Epic 4.
- **State, and it is honoured.** `proposed` → `done` or `archived`, and an archived recommendation NEVER comes back. Same rule as the hand-edited knowledge base and the erasure tombstone: once the user has decided, do not ask again.

Identity is a stable `key`, never the wording — a re-analysis that rephrases the same idea must recognise it or the list fills with duplicates.

State is driven by conversation: the user says "done" or "not interested" and the agent updates it. Nobody grooms a backlog — that boundary is what keeps this out of task-manager territory, which §8 lists as out of scope. `laravel/ai` already persists conversations (`RemembersConversations`, with its own migration), so what remains to build is the tool the agent calls to change a state.

## A queued agent opens its agent_runs row as pending, at dispatch
`RecordsAgentRun` writes its row when the provider call starts, so between a user clicking and a worker picking the job up there is nothing to report — a screen cannot tell "queued" from "never happened". `AgentRunStatus::Pending` is that gap and exists for it.

Whoever queues the job creates the row (`status: Pending`, `agent: SomeAgent::slug()`) and passes it to the job; the action hands it to the agent with `recordInto($run)`, and the middleware CLAIMS it instead of opening a second one — one invocation stays one row, which is what the meter counts. The job's `failed()` marks the row failed so a crash before or after the call does not leave it pending for good.

Do not track job state in the cache: a deploy or a Redis flush wipes it, and queue state is not the job's to hold. `AgentRun::isInFlight()` also refuses to believe a run older than 15 minutes — with no worker draining the queue the row would otherwise spin a UI forever.
