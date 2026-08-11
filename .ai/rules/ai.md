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
