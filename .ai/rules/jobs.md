---
paths:
  - 'app/Jobs/**'
---

# Jobs

## Queues: Redis + Horizon, one queue per rhythm
ADR-011. Redis is the queue, cache and lock driver; `laravel/horizon` runs the workers. Compose runs Horizon, never a bare `queue:work`. Horizon dashboard is superadmin-only.

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
Discovery fans out into queued jobs rather than running one long agent tool loop — see `.ai/rules/discovery.md` for why. Consequences that bind every job under `app/Jobs/Discovery/`:

- **Each job carries its own minimal context** and re-reads what it needs. Never pass a page body between jobs; pass ids. That is what keeps the token cost flat instead of quadratic.
- **Every job is idempotent.** On pickup it checks `crawled_pages` / the target row and returns early if the work is already done. A re-run must produce the same result, because the UI exposes a per-step re-run button.
- **Every job checks `discovery_runs.status` first** and returns immediately on `exhausted`, `cancelled`, `failed`. That one flag is both the credit ceiling and the cancel button; there is no job registry and no worker gets killed.
- **State lives in `discovery_tasks`, not in Laravel's `jobs` table**, which drops the row on success and so cannot back a history, a cost breakdown, or a re-run button. `ShouldBeUnique` still guards against double-enqueue.
- **A failing job fails its own row, never the run.** One unreadable directory must not cost the leads already found — a single malformed page (a NUL byte in a response body is enough) otherwise takes down the whole run.

## Horizon is installed: one supervisor per queue, and retry_after outlasts them all
`laravel/horizon` runs the workers, and `compose.yaml` has a `horizon` service in the app image — nothing queued moves without that container up. Locally: `sail up -d`, or `sail artisan horizon` if you want the log in front of you.

`config/horizon.php` defines one supervisor PER QUEUE (`discovery`, `crawl`, `ai`, `sending`, `imap`, `default`), not one pool over all of them: each is limited by something different, and a shared pool lets the noisiest starve the rest. `sending` is deliberately capped at one process — bursty cold outreach is what gets a mailbox blocked. `crawl` concurrency is how many requests one site sees at once, since the politeness delay is per worker.

The trap: `retry_after` on the redis queue connection must outlast the LONGEST supervisor `timeout` (currently `ai` at 900s, so it is 1200). Get that order wrong and a slow model call is handed to a second worker while the first is still running — which for the `sending` queue means the same outreach mail goes out twice.

Adding a supervisor means adding it to `defaults` AND to both `environments` blocks; `environments` merges the keys you name into `defaults` rather than replacing the block.

The `/horizon` dashboard shows every job payload (lead names, addresses, message bodies), so the gate is `is_super_admin` — instance scope, never granted through an organization. Horizon 5.48 no longer publishes assets; there is nothing to add to `post-update-cmd`. Metrics stay blank without the scheduled `horizon:snapshot` in `routes/console.php`, which means the scheduler has to be running too.
