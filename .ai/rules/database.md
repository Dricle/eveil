---
paths:
  - 'database/**'
---

# Database

## PostgreSQL only — never SQLite, not even in tests
ADR-010, settled 2026-08-10. Postgres in dev, test, CI and prod. The Laravel starter shipped with SQLite; it is being replaced, do not reintroduce it.

Why Postgres: discovery runs write concurrently from several workers for minutes at a time and SQLite allows one writer at a time even in WAL. The schema is JSON-heavy (`knowledge_base`, `icps.criteria`, `agent_runs.input/output`, `campaign_steps.config`) and JSONB is indexable. Dedup needs partial unique indexes; lead search will need native full-text; pgvector stays available if the knowledge base ever needs embeddings.

Why tests too: SQLite tests against a Postgres prod diverge on exactly what this schema leans on — JSONB semantics, case sensitivity, partial indexes, transactional DDL. Tests pass, prod breaks. Running the suite therefore requires a reachable Postgres; `composer setup` must fail loudly if there is none rather than silently falling back.

Use Postgres-specific features freely (JSONB operators, partial indexes, full-text) — portability is explicitly not a goal.

## Migration conventions, as established 2026-08-11
The v0 schema lives in five grouped migrations (`2026_08_11_1000xx_*`): organizations+projects, agent_runs, project knowledge, discovery, outreach. Group related tables in one file rather than one file per table — Laravel's own users migration does the same.

- **`jsonb()`, never `json()`.** `json()` on Postgres is not indexable.
- **Status columns are plain `string()` cast to PHP enums in the model, never `enum()`.** On Postgres `enum()` generates a CHECK constraint that needs a drop-and-recreate for every new value, on a schema that will keep moving. Document the allowed values in a trailing `//` comment.
- **Shape comments use `//`, not `/** @var */`.** PHPStan rejects a `@var` with no variable name, and these describe a column's JSON payload rather than a PHP variable.
- **Partial unique indexes need raw `DB::statement`** — Blueprint has none. Two exist and both encode business rules: `leads_project_id_email_unique` (dedupe by email while allowing many LinkedIn-only rows with no email) and `campaign_leads_one_active_per_lead` (a lead sits in at most one live campaign, ADR-015). `tests/Feature/SchemaConstraintsTest.php` proves both actually enforce — keep it passing.
- Credit tables (`credit_prices`, `credit_wallets`, `credit_transactions`) are deliberately NOT created yet: cloud-only, and cloud does not exist in v0 (ADR-019).

## Environment precedence — two behaviours that bite in opposite directions
Verified empirically 2026-08-11, not assumed. They interact, and getting them backwards produces silent wrongness rather than an error.

- **Laravel's Dotenv is immutable**: a real environment variable WINS over the same key in `.env`. That is what lets CI point at its own Postgres while `.env.example` keeps Sail's internal `pgsql` hostname.
- **PHPUnit's `<env>` does NOT overwrite an existing environment variable** (no `force="true"`). So exporting `DB_DATABASE` in CI would silently run the suite against that database instead of the `testing` one `phpunit.xml` asks for — no error, just the wrong target and a wiped database.

Consequence for `.github/workflows/tests.yml`: set `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD` at job level, and **never `DB_DATABASE`**. `.env.example` supplies it for the migrate step; `phpunit.xml` supplies `testing` for the suite. The workflow's Postgres service only creates `eveil`, so a step creates `testing` before setup runs.
