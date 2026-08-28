---
paths:
  - 'app/Ai/**,app/Http/Controllers/AppSettings/**'
---

# App Settings

## Agent cost visibility: tokens only, no $/token rate table
The Agents settings screen (`app-settings.agents.index`) shows average tokens in/out per call (SQL `avg()`, computed live, never stored). It does NOT convert that to a dollar estimate, and does not add a $/token rate lookup anywhere (settings, config, or hardcoded) - explicitly rejected when raised, per the existing "tokens, never money" rule in `.ai/rules/ai.md`. The operator reads the token averages and does the $ conversion themselves, choosing whatever provider/pricing they want to reason against. If asked again to "show cost in dollars" on this screen, this was a deliberate decision, not an oversight - don't silently add a price table without raising it again.

Credit price calibration (`credit_prices`, ADR-019) is separate and fine to edit: `CreditPriceController::store` always adds a new versioned row, never edits in place.
