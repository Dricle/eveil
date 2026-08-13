---
paths:
  - 'app/Cloud/**'
---

# Cloud

## AI credits: cloud only, priced per unit of work
ADR-019, settled 2026-08-11.

Self-hosted: the superadmin supplies their own API key. NO credit tracking, NO billing, no counting code at all. `agent_runs` still exists there for debug and history — the ledger does not.

Cloud: the user buys credits; each action debits a credit count from a DB table that cloud superadmins can adjust without a redeploy. Users only ever see credits — never tokens, never a model name. The AI provider and model are configured in-app and switchable in both editions; credits decouple the displayed price from real cost, so changing LLM moves our margin, not the user's price.

Anchor: 1000 credits ≈ $1 of internal AI cost. Base grid — project.analyze 150 (per site analysis), targets.derive 60, discovery.plan 500 (per run), company.qualify 4 (per company EVALUATED), contact.extract 8 (per company retained), sequence.generate 100, lead.personalize 3, reply.classify 1. Email verification, SMTP send and IMAP read cost 0 — a selling point, competitors bill verification. A 100-lead campaign ≈ 3500 credits.

Bill per unit of work, never per "run": a run evaluating 400 companies costs ten times one evaluating 40.

Implementation rules:
- Reserve up front, settle after. A run holds its ceiling, consumes, then releases the remainder — it cannot debit at the end or it gets cut off mid-flight. In cloud the run's hard budget (ADR-004) IS the credit hold: one mechanism, not two.
- `credit_prices` is versioned with `effective_from`, never edited in place, and each transaction freezes the rate charged at debit time. Otherwise a price change reprices history and billing stops being reproducible.
- A run aborted by our own error is not billed; a run the user interrupts bills the work actually produced.

## Cloud pricing: credits only, 3x markup, guarded trial
ADR-024, settled 2026-08-11. Credits are the only cloud model — no bring-your-own-key tier. Anyone who wants to supply their own key runs the self-hosted edition, which is free and built for it.

Calibration: 1000 credits = $1 real cost; a 100-lead campaign = 3500 credits ≈ €3.50. Target margin 3× → ~€0.10 per qualified lead, enrichment and sequencing included (cheaper than Apollo, which is $0.05–0.15 per exported contact, for more product).

A miscalibrated plan loses money silently — AI is the entire variable cost, nothing else absorbs it. €29/mo including 25,000 credits would cost us €25: 15% margin. Always check a plan's included credits against real cost before shipping it.

Provider price rises are absorbed by ADR-019: add a new `credit_prices` row with a later `effective_from` raising credits-per-action. Customers' consumption goes up, their rate does not.

Trial: ~5000 credits at signup, enough for one complete campaign through to replies — the self-hosted edition is free, so a trial that stops short of the first reply convinces nobody.

The trial grant is a REAL abuse vector: this product is an email-extraction machine and 5000 free credits is ~100 qualified leads. Mandatory guards — verified email, one project only, a cap on leads DISCOVERED (not just credits), and no CSV export before a first payment. Trial users can see and email their leads; they do not walk away with the file.

Expiry: subscription credits expire at period end, purchased packs last 12 months. Without expiry you accrue a liability of unspent credits bought at old rates.

## Cloud is born smart; self-hosted starts cold. That is a selling point, not a limit
Noted 2026-08-13. `known_hosts`, `crawled_pages` and the listing-extraction cache are shared instance-wide. In cloud that means ONE registry fed by every customer: someone prospecting restaurants pays a model to work out that a national directory is an index, and the next customer prospecting bakeries gets it free. A cloud account is useful from the first run in a way a fresh self-hosted install cannot be, because the self-hosted install has nobody else's learnings.

**This is a legitimate commercial argument and it must stay an emergent one.** Self-hosted ships the identical code and the identical seed registry — nothing is withheld, no cap, no crippled path. It simply has a smaller population feeding it. The moment someone "improves" this by shipping a thinner seed to self-hosted, it becomes an artificial limit and breaks the core-stays-free promise. Do not.

Use it in positioning: cloud saves you the cold start, plus hosting, plus the AI key. Not: cloud unlocks features.

