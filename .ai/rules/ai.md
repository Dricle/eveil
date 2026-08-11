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

## The AI layer as built 2026-08-11
`laravel/ai` is pinned to an exact version (`0.10.3`) and reached through exactly one class: `App\Ai\AgentRunner`. Do not call `->prompt()` from anywhere else — the package is pre-1.0 and every call must land in `agent_runs`.

- Agents live in `app/Ai/Agents/`, implement `Laravel\Ai\Contracts\Agent` (which already declares `prompt()` — no intersection type needed) plus `HasStructuredOutput`, and use the `Promptable` trait. Schema fields are declared with `Illuminate\Contracts\JsonSchema\JsonSchema`.
- `AgentRunner::resolve(AgentType)` is the single source of the provider/model pair. It reads `config('eveil.agents.*')` today and the superadmin's per-agent override later (ADR-026) — which is why no call site reads that config directly.
- Cost comes from `config('eveil.pricing.*')`, US dollars per million tokens, with cache reads at 0.1x and cache writes at 1.25x. An unpriced model costs 0 rather than throwing: a missing rate must never break a run, and the zero is visible in `agent_runs`.
- `tokens_in` sums prompt + cache-read + cache-write tokens, so the meter reflects what was actually sent.
- Faking in tests: `MyAgent::fake([$arrayOfStructuredOutput])`, or pass a `StructuredTextResponse` with a real `Usage` when the test needs token or cost assertions — the array form yields zero usage.

## Two things the first live runs taught (2026-08-11)
- **The 60s HTTP default is not enough for a thinking model.** The first real `icp.derive` took 69s and died on it. Timeouts are per agent in `config('eveil.agents.*.timeout')`: 300s for the planner, 60s for the cheap read-and-extract agents, where a long timeout would only mean a stuck job holding a worker. Always pass it — `AgentRunner` does.
- **Output tokens are where Opus costs money**, at 25 $/MTok against 5 $ for input, and generative tasks produce more than they consume: the ICP derivation returned 4 833 output tokens for 4 456 input. Both first measurements came in above estimate (`project.analyze` 0.15 → 0.192 $, `icp.derive` 0.06 → 0.143 $) for that single reason. When estimating a new action's credit cost, size the OUTPUT first — the remaining grid lines are still guesses and are probably low.

## The mapping lives in the database, config is only the default
Implemented 2026-08-11. `App\Support\Settings` reads the `settings` table, cached forever and flushed on write; `AgentRunner::resolve()` and `::timeout()` merge the stored override onto `config('eveil.agents.*')`. Nothing else reads that config — switching a model is a settings change, never a deploy.

- A partial override merges: setting only `model` keeps the timeout, which is what stops a thinking model dying on the 60s HTTP default.
- A stored value of the wrong shape is ignored rather than trusted — the settings screen writes it, so validate on read.
- The cache is invalidated on write. Without that a change from the screen appears to do nothing until the next deploy.
- `php artisan eveil:agent-model` is the command-line half: no argument lists every agent with its provider, model, timeout, whether it came from `default` or `database`, and what it has spent so far. The superadmin settings PAGE still has to be built — it belongs with Epic 1, once auth and the UI shell exist.
