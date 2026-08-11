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
