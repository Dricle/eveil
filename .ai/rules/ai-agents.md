---
paths:
  - 'app/Models/DiscoveryRun.php,app/Jobs/Discovery/ReflectAndExpand.php,app/Ai/Agents/DiscoveryPlanner.php'
---

# Ai Agents

## ReflectAndExpand is built: mid-run focus on a host that proved productive, profile-blind by construction
`.ai/rules/discovery.md`'s job-graph list named `ReflectAndExpand` as aspirational ("AI, reads aggregates, enqueues the next wave"); it now exists.

`DiscoveryRun::finishIfIdle()` checks `mayReflectAndExpand()` BEFORE `mayPivotSource()` (success case checked first; they're mutually exclusive by construction anyway). Trigger: `productiveHosts()` finds >=3 qualified companies sharing one host (grouped by `company.source_url`'s host, not `company.domain` - a harvested listing's candidates each have their own domain but share the listing's `source_url`) with avg fit_score >= 70, one shot per run (mirrors `mayPivotSource()`'s own one-shot guard), only for `DiscoveryRunOrigin::Search`.

Deliberately NEVER written to `known_hosts`: whether a host is productive is a fact about ONE target profile, and the registry is profile-blind by design (ADR-033) - Product Hunt is gold for a SaaS-selling profile and noise for a restaurant one. `productiveHosts()` is a live query against THIS run's own `company_target_evaluations`, nothing persisted, nothing shared across runs or profiles.

Reuses the existing `DiscoveryPlanner`/`Planner::plan()` agent (new `?Collection $productiveHosts` param, rendered into its own prompt section by the agent itself, not the caller) rather than a new agent class - same reuse of `PlanDiscovery`'s machinery that `pivotSource()` already does for the opposite (empty-run) case.
