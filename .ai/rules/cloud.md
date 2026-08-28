---
paths:
  - 'app/Cloud/**'
---

# Cloud

## AI credits: cloud only, priced per unit of work
ADR-019.

Self-hosted: the superadmin supplies their own API key. NO credit tracking, NO billing, no counting code at all. `agent_runs` still exists there for debug and history: the ledger does not.

Cloud: the user buys credits; each action debits a credit count from a DB table that cloud superadmins can adjust without a redeploy. Users only ever see credits: never tokens, never a model name. The AI provider and model are configured in-app and switchable in both editions; credits decouple the displayed price from real cost, so changing LLM moves our margin, not the user's price.

Anchor: 1000 credits ≈ $1 of internal AI cost. Base grid: project.analyze 150 (per site analysis), targets.derive 60, discovery.plan 500 (per run), company.qualify 4 (per company EVALUATED), contact.extract 8 (per company retained), sequence.generate 100, lead.personalize 3, reply.handle 1 (an agent with tools, to be re-measured). Email verification, SMTP send and IMAP read cost 0: a selling point, competitors bill verification. A 100-lead campaign ≈ 3500 credits.

Bill per unit of work, never per "run": a run evaluating 400 companies costs ten times one evaluating 40.

Implementation rules:
- Reserve up front, settle after. A run holds its ceiling, consumes, then releases the remainder: it cannot debit at the end or it gets cut off mid-flight. In cloud the run's hard budget (ADR-004) IS the credit hold: one mechanism, not two.
- `credit_prices` is versioned with `effective_from`, never edited in place, and each transaction freezes the rate charged at debit time. Otherwise a price change reprices history and billing stops being reproducible.
- A run aborted by our own error is not billed; a run the user interrupts bills the work actually produced.

## Cloud pricing: pay-as-you-go, no plans, no subscription
ADR-024, revised: credits are the only cloud model, and there is no tiered/subscription layer above them - no plan to pick, no Stripe subscription, no recurring invoice. A customer tops up whatever dollar amount they choose, converted to credits at one flat, published rate (`billing.credits_per_dollar`, superadmin-adjustable). Anyone who wants to supply their own key runs the self-hosted edition, which is free and built for it.

Calibration: 1000 credits = $1 real cost. Target margin 3× → ~€0.10 per qualified lead, enrichment and sequencing included (cheaper than Apollo, which is $0.05–0.15 per exported contact, for more product). A miscalibrated rate loses money silently: AI is the entire variable cost, nothing else absorbs it - always check `billing.credits_per_dollar` against real cost before changing it.

Provider price rises are absorbed by ADR-019, not by the top-up rate: add a new `credit_prices` row with a later `effective_from` raising credits-per-action. Consumption per dollar goes down; the published rate does not move under an existing customer's feet.

Auto top-up (`Organization::auto_topup_threshold`/`auto_topup_amount_cents`) is the pay-as-you-go answer to a subscription's auto-renewal: an off-session Stripe charge against the card already on file, checked after every debit (`AutoTopUp::maybeTrigger`, called from `CreditSpendGuard::charge()`), never inside `Organization::debit()`'s transaction - the Stripe call is a network round-trip and must not hold a DB lock. Concurrency is guarded the same way as the balance itself: `Organization::claimAutoTopUpLock()` is one atomic `UPDATE`, not a check-then-charge.

Trial: ~5000 credits at signup, enough for one complete campaign through to replies. The self-hosted edition is free, so a trial that stops short of the first reply convinces nobody.

The trial grant is a REAL abuse vector: this product is an email-extraction machine and 5000 free credits is ~100 qualified leads. Mandatory guards: verified email, one project only, a cap on leads DISCOVERED (not just credits), and no CSV export before a first payment. Trial users can see and email their leads; they do not walk away with the file.

Credits never expire, whether from the trial grant or a top-up - a customer who buys 100 credits can spend them years later. This is a deliberate liability (unspent credits bought at old rates), accepted for the goodwill of "credits you paid for are always yours," same posture as OpenAI/Anthropic API credits.

## Cloud is born smart; self-hosted starts cold. That is a selling point, not a limit
`known_hosts`, `crawled_pages` and the listing-extraction cache are shared instance-wide. In cloud that means ONE registry fed by every customer: someone prospecting restaurants pays a model to work out that a national directory is an index, and the next customer prospecting bakeries gets it free. A cloud account is useful from the first run in a way a fresh self-hosted install cannot be, because the self-hosted install has nobody else's learnings.

**This is a legitimate commercial argument and it must stay an emergent one.** Self-hosted ships the identical code and the identical seed registry: nothing is withheld, no cap, no crippled path. It simply has a smaller population feeding it. The moment someone "improves" this by shipping a thinner seed to self-hosted, it becomes an artificial limit and breaks the core-stays-free promise. Do not.

Use it in positioning: cloud saves you the cold start, plus hosting, plus the AI key. Not: cloud unlocks features.

