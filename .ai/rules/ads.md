---
paths:
  - 'app/Services/Ads/**'
  - 'app/Models/Ad*.php'
  - 'app/Models/PlatformApp.php'
  - 'app/Enums/Ad*.php'
  - 'app/Actions/*Ad*.php'
  - 'app/Ai/Agents/AdStrategist.php'
  - 'app/Http/Controllers/Ad*.php'
---

# Ads

## The agent proposes, code applies
`AdStrategist` never calls a network. Its tools are thin wrappers over `App\Services\Ads\AdProposals`, the ONLY way an `ad_decisions` row is created (the budget stop uses it too). `AdProposals` validates, stores drafts for a launch or a new ad, runs `AdGuardrails`, then queues `ApplyAdDecision` or leaves the decision for a person. `App\Actions\ApplyAdDecision` is the only place a network is told to change anything. Do not add a tool or a controller that touches a driver directly.

Approval is per decision type (`AdDecisionType::needsApproval()`) against `projects.ads_autonomy_level`. Cutting spend (pause, lower budget) never waits, at any level.

## One budget per project, the agent allocates
The user sets ONE monthly ads budget on the project (`projects.ads_monthly_budget_micros`, in `ads_currency`), never one per account or per network: splitting it between networks is the agent's job, from the numbers. An account only says whether Eveil may use it (`ad_accounts.enabled`). Usable = connected, enabled, and billed in the budget's currency (`Project::usableAdAccounts()`): budgets are never converted between currencies. Spend and pacing are summed over every account, in the instance's month.

## Guardrails are code, checked twice
`AdGuardrails::refusal()` runs when proposed (so the agent hears the reason in the same run) and again just before applying (the account may have spent since). Budget pacing: running daily budgets times days left must fit what is left of the month. Raises are capped per step; new campaigns per account per week are capped. Knobs live in instance settings `ads.policy.*`, seeded by the migration. `EnforceAdBudget` pauses everything once the month is spent, after every hourly sync.

## Adding a network
One `AdPlatform` case and one class implementing `AdClientInterface`. Nothing else names a network. Drivers talk REST through `Http`, no SDKs. OAuth apps come from `platform_apps` (organization row wins over the instance row, `PlatformApp::resolve()`), never from a per-network settings class.

## Numbers for the agent
`AdPerformanceReport` computes every ratio, Wilson interval and verdict (`AdClassifier`); the agent only judges. Be generous with numbers there, the user asked for it. Money is micros in the database and currency units everywhere a person or a model reads it.

## Eveil only touches what it created
One ad group per campaign, created by Eveil. Metrics sync keeps only rows for ads Eveil created. Never adopt or edit campaigns the user runs by hand in the same account.
