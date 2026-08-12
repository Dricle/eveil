---
paths:
  - 'app/Jobs/**'
---

# Jobs

## Queues: Redis + Horizon, one queue per rhythm
ADR-011, settled 2026-08-10. Redis is the queue, cache and lock driver; `laravel/horizon` runs the workers. Compose runs Horizon, never a bare `queue:work`. Horizon dashboard is superadmin-only.

Named queues, each with its own concurrency — do not dump jobs on `default`:
- `discovery` — may saturate workers, bounded by the run budget
- `crawl` — throttled per domain, respects robots.txt
- `ai` — isolated so provider rate-limits never block anything else
- `sending` — deliberately slow and spread across the day, never bursty
- `imap` — regular polling
- `default` — everything else

Use Redis for the per-domain crawl rate limiter and for distributed locks: two workers must never process the same email account or the same domain at once.

Deliberately rejected: the Postgres `database` driver. It would hold up on a solo instance (SKIP LOCKED), but locks and rate limiting become hot-row contention and cross-queue balancing stays manual.

## Discovery jobs are graph nodes, and the row is the UI (ADR-033)
Settled 2026-08-12. Discovery fans out into queued jobs rather than running one long agent tool loop — see `.ai/rules/discovery.md` for why. Consequences that bind every job under `app/Jobs/Discovery/`:

- **Each job carries its own minimal context** and re-reads what it needs. Never pass a page body between jobs; pass ids. That is what keeps the token cost flat instead of quadratic.
- **Every job is idempotent.** On pickup it checks `crawled_pages` / the target row and returns early if the work is already done. A re-run must produce the same result, because the UI exposes a per-step re-run button.
- **Every job checks `discovery_runs.status` first** and returns immediately on `exhausted`, `cancelled`, `failed`. That one flag is both the credit ceiling and the cancel button; there is no job registry and no worker gets killed.
- **State lives in `discovery_tasks`, not in Laravel's `jobs` table**, which drops the row on success and so cannot back a history, a cost breakdown, or a re-run button. `ShouldBeUnique` still guards against double-enqueue.
- **A failing job fails its own row, never the run.** One unreadable directory must not cost the leads already found — the same lesson as the NUL-byte crash of 2026-08-11.

