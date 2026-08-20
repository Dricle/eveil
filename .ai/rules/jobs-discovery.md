---
paths:
  - 'app/Jobs/Discovery/**'
---

# Jobs Discovery

## The discovery graph: how a node behaves and where the budget lives
Every node extends `App\Jobs\Discovery\DiscoveryJob` and implements `execute()` only. The base reads `discovery_runs.status` first (terminal → the row is marked `skipped` and nothing runs, which is how cancel and the credit ceiling work), stamps the `discovery_tasks` row running/succeeded/failed, and calls `finishIfIdle()` on the way out so whichever node happens to be last closes the run. No supervising job, nothing to poll.

`execute()` returns counters that land on the row as `result`; throwing marks that row failed and the run carries on: only `PlanDiscovery` overrides `failsRun()`, because with no plan there is nowhere to look. `skip($why)` (a `TaskSkipped`) is for "the budget line was spent" or "already done", and is not a failure.

Budget lines are COLUMNS on `discovery_runs` (`queries_used`, `candidates_found`, `pages_used`, `qualified_count`), spent through `claim('max_pages')`: a single `UPDATE … RETURNING` statement. Never read-then-write a counter: several workers spend the same envelope at once, and in cloud that is money that was not held. `stats` stays the summary written when the run closes, aggregated from the task rows.

Children go out through `fork()`, which writes the row before dispatching so a run is never briefly idle-looking. A node that makes a model call opens its `agent_runs` row with `meter()` first: same pending-row rule as any queued agent.

## The per-run budget is not credits, and it exists in both editions
`discovery_runs.budget` caps one run's searches, pages, candidates and kept companies. It is NOT the cloud credit ledger (ADR-019, cloud-only): a run on a self-hosted box spends the operator's own provider key and hammers other people's sites, so an unbounded fan-out is a bill and a ban, not a billing question. Do not gate it on `APP_EDITION`.

Because of that, nothing a node writes may read as a paywall. A skip says which ceiling it hit and in the numbers the run was given: "not run. This search is past the 12 searches one run may make": never "budget spent".

`claim()` uses a conditional `UPDATE … WHERE counter < limit`, so a counter can never pass its cap: incrementing first and refusing afterwards left the screen reporting "22 searches of 12", which reads as a broken app rather than a cap working.

The planner is TOLD the cap (`Planner::plan($profile, $run->limit('max_queries'))`, and the agent's instructions explain that map probes and web queries count against one number). It is told rather than trimmed afterwards: a planner that knows it has twelve spends twelve on the best areas, where trimming a plan of eighty discards whichever ones the model happened to list last. Keep the plan whole on the task row either way: a skipped probe names the search it would have made, so raising the cap and replaying it runs exactly that.
