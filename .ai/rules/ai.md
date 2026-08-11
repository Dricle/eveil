---
paths:
  - 'app/Ai/**'
---

# Ai

## Agents are queued jobs, not daemons — and always metered
An "agent" here = a prompt + a toolset + a queued job. Nothing long-running, no persistent process per project.

Every agent invocation writes an `agent_runs` row: project_id, type, status, input, output, tokens_in, tokens_out, cost, duration, error. Non-negotiable and needed from day 1 — it is simultaneously the debug log, the analysis history (Epic 4), and the billing meter (Epic 12). Retrofitting it is painful.

Every run carries a hard budget (max tokens, max pages fetched, max leads produced) and aborts when hit. An unbounded agent loop that fetches pages burns real money.

Call laravel/ai through our own service classes, never inline in controllers or jobs — it is pinned pre-1.0 and breaks between minors.

## Provider and model are configurable per agent class
ADR-026, settled 2026-08-11. The superadmin picks provider + model for EACH agent class from a settings screen. `laravel/ai` supplies the provider/model list; the agent list comes from the code — do not invent a parallel role taxonomy.

Shipped defaults (a fresh install must work without opening the screen): Planner = Opus 5 (where to search, ICP derivation, sequence generation); Extractor, Qualifier, Writer, Classifier = Haiku 4.5.

Extractor, Qualifier and Classifier REQUIRE reliable structured output — mark them as such in the UI. A small local model wired to Extractor via Ollama produces BROKEN extractions, not merely worse ones. Planner degrades gracefully; the others do not.

This is an INSTANCE-scope setting (ADR-003), superadmin-only, like the provider key — no organization admin or member ever sees it. In cloud the only superadmin is the operator, so a customer can never change the mapping.

Operational note, not a product guard: the credit grid (ADR-019) is calibrated on this exact model mix, and switching Qualifier to Opus 5 multiplies the real cost of `company.qualify` by five. If the operator changes the mapping in cloud, they adjust `credit_prices` in the same move.

Fallback: Horizon backoff and retry, NO automatic cross-provider failover. The workload is asynchronous so nobody is waiting on a screen, and failing over mid-run would score leads on two different scales invisibly. Switching provider stays a deliberate config change.

## Use laravel/ai directly — there is no wrapper
Corrected 2026-08-11 after review. An earlier `AgentRunner` wrapper was deleted: the package already provides every hook it reinvented, and treating a mature package as fragile because of its version number cost clarity for nothing.

The three extension points that matter, none of them obvious from the docs:
- **`Promptable` calls `provider()`, `model()` and `timeout()` on the agent** before falling back to its own `#[Provider]` / `#[Model]` / `#[Timeout]` attributes (see `getProvidersAndModels()` / `getTimeout()` in the trait). That is where the database-backed mapping plugs in (ADR-026), so a model change is a settings change and not a deploy.
- **`HasMiddleware`** wraps the call, so it can record a run, catch a throwing provider, and read the response. Metering rides on it rather than on the `AgentPrompted` event, because an event listener never sees the failure and would leave rows stuck on "running".
- **`AgentPrompt` carries `readonly Agent $agent`**, which is how middleware reaches the project an agent was constructed for.

Shape: one agent class per specialisation in `app/Ai/Agents/`, all extending `EveilAgent` (constructed with the `Project`, declares its `AgentType`), implementing `HasStructuredOutput`. Call them plainly — `(new WebsiteAnalyst($project))->prompt($text)`. Supporting pieces: `AgentSettings` (database over config), `ModelPricing` (cost), `Middleware\RecordsAgentRun` (the `agent_runs` row).

- Cost comes from `config('eveil.pricing.*')`, dollars per million tokens, cache reads at 0.1x and writes at 1.25x. **Price on the model REQUESTED, not the one echoed back**: providers answer with a dated id (`claude-haiku-4-5-20251001`) and an exact lookup silently meters a live call at zero. `ModelPricing` also falls back to the longest matching prefix.
- `tokens_in` sums prompt + cache-read + cache-write tokens, so the meter reflects what actually crossed the wire.
- Faking in tests costs nothing: `MyAgent::fake([...])` swaps the gateway, no HTTP leaves the process, and `phpunit.xml` holds a dummy `ANTHROPIC_API_KEY` so an escapee would 401 rather than bill. Pass a `StructuredTextResponse` with a real `Usage` and `Meta` when asserting on tokens or cost — the plain-array form yields zero usage, which is exactly the blind spot that hid the dated-id bug.

## Two things the first live runs taught (2026-08-11)
- **The 60s HTTP default is not enough for a thinking model.** The first real `icp.derive` took 69s and died on it. Timeouts are per agent in `config('eveil.agents.*.timeout')`: 300s for the planner, 60s for the cheap read-and-extract agents, where a long timeout would only mean a stuck job holding a worker. `EveilAgent::timeout()` returns it, and `Promptable` picks it up.
- **Output tokens are where Opus costs money**, at 25 $/MTok against 5 $ for input, and generative tasks produce more than they consume: the ICP derivation returned 4 833 output tokens for 4 456 input. Both first measurements came in above estimate (`project.analyze` 0.15 → 0.192 $, `icp.derive` 0.06 → 0.143 $) for that single reason. When estimating a new action's credit cost, size the OUTPUT first — the remaining grid lines are still guesses and are probably low.

## The mapping lives in the database, config is only the default
Implemented 2026-08-11. `App\Support\Settings` reads the `settings` table, cached forever and flushed on write; `App\Ai\AgentSettings` merges the stored override onto `config('eveil.agents.*')` and is what `EveilAgent::provider()/model()/timeout()` return. Nothing else reads that config — switching a model is a settings change, never a deploy.

- A partial override merges: setting only `model` keeps the timeout, which is what stops a thinking model dying on the 60s HTTP default.
- A stored value of the wrong shape is ignored rather than trusted — the settings screen writes it, so validate on read.
- The cache is invalidated on write. Without that a change from the screen appears to do nothing until the next deploy.
- `php artisan eveil:agent-model` is the command-line half: no argument lists every agent with its provider, model, timeout, whether it came from `default` or `database`, and what it has spent so far. The superadmin settings PAGE still has to be built — it belongs with Epic 1, once auth and the UI shell exist.
