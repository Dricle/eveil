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
