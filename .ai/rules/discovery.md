---
paths:
  - 'app/Discovery/**'
---

# Discovery

## Lead discovery: the differentiator, and its stack
AI lead discovery is the product's edge — not database size (Apollo has 275M contacts, we never will). Our edge: ICP derived automatically from the project knowledge base, fresh long-tail results (local businesses, new companies, niche directories) and shared context between discovery and personalisation.

Pipeline, five stages, each independently testable: ICP derivation → company discovery → qualification/fit scoring → contact + email discovery → verification.

Settled 2026-08-10:
- Search: SearXNG as an extra docker compose service. Free, no API key, identical code in both editions. Watch for rate-limits/blocking; a paid driver (Brave/Serper) can be added behind the same interface if it proves unreliable.
- Also free and key-less: OpenStreetMap Overpass API for local-business discovery, GitHub API for developer ICPs.
- Email verification: in-house MX check + SMTP `RCPT TO` probe, no send. Must detect catch-all domains and flag those addresses `risky` rather than `valid`. Gmail/Outlook block probes — treat as unknown, never as invalid.
- Email addresses from pattern inference (one known address on a domain → derive `first.last@`) are stored with `email_source=inferred` and always verified before any send.

Fetching starts with plain HTTP only. Add a headless browser container only once the JS-render failure rate is measured, not before. Respect robots.txt and rate-limit per domain. Dedupe companies by domain and contacts by email from the first line of code — a re-run must never duplicate.

## Per-project isolation, shared raw page cache
ADR-014, settled 2026-08-10. Companies and leads stay scoped to their project — no shared company registry. Re-fetching is avoided by an instance-level raw page cache: key = normalised URL, with a TTL, public content only.

Why no shared registry: the expensive part of discovery is LLM qualification, not the HTTP fetch, and fit score plus its rationale are ICP-specific — the same company scores 90 for one product and 20 for another. Sharing would only save the ICP-independent part (page content, firmographics) at the cost of an extra entity, a join everywhere, and an arbitration rule for when two projects disagree on a company's facts.

Cache rules: never store authenticated or logged-in content, key on the URL, honour the TTL. It is public web data, so it is safe to share across tenants in cloud; if that ever becomes a concern, scope the cache per organization — nothing else changes. The cache pays off on re-runs of a single project as much as across projects.

## ICPs: as many as the agent derives, free CRUD
ADR-015, settled 2026-08-10. An ICP (Ideal Customer Profile) is the structured portrait of the target customer — sectors, size, geography, job titles, technologies, trigger signals — derived from the project knowledge base. It drives where the agent searches and how each company is scored.

The agent derives as many as it judges necessary (no imposed count) and the user can create, edit and delete them freely. A product usually serves several markets; flattening them into one average profile targets nobody.

Schema consequences, non-negotiable:
- Fit score does NOT live on the company. The same company scores 90 for one ICP and 20 for another. Split `companies` (firmographic facts, deduped by domain within the project) from `company_icp_evaluations` (company_id + icp_id → fit_score, fit_reason). Otherwise two ICPs finding the same company overwrite each other's evaluation.
- A lead surfaced by two ICPs is not contacted twice: a lead belongs to at most one active campaign per project. The second ICP records the overlap without re-engaging.
- Each active ICP is one more discovery run, so one more budget. No hard cap, but the UI must show expected cost when several are active.

The main screen stays a straight line — the CRUD is available, never required to move forward.

## Insufficient discovery: diagnose before widening
ADR-020, settled 2026-08-11. "Found nothing" is four distinct failures and they need different responses:
- ICP too narrow (3 companies instead of 100) → widen one criterion.
- Wrong source (0 results but the market exists) → switch tool, not criteria.
- Fit uniformly low (300 found, none above 40) → **the ICP is wrong; NEVER widen** — escalate to the user. Widening here is the worst case: 100 off-target leads get contacted and the user's domain takes the complaints.
- No emails (companies qualified, contacts unreachable) → extraction problem, not targeting.

Market exhaustion is a RESULT, not a failure. "Your market is 40 companies, here they are" beats scraping noise to hit a quota — no competitor says this, they sell on volume.

Widening is indexed on `projects.autonomy_level` (ADR-009): supervised proposes and waits; semi_auto and autonomous widen alone and report what they relaxed. Shared bounds for all three: one axis at a time, two steps maximum, in this order — geography → size → adjacent sectors → job titles. Never two axes at once or you cannot tell what worked. Log and display every relaxation.

Widening attempts count against the ORIGINAL run's budget and never open a new one — otherwise the loop burns credits producing nothing.
