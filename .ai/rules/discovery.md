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

## The crawler as built 2026-08-11
`SiteCrawler` fetches the homepage, then follows a handful of links scored by path (`about`, `pricing`, `features`… ahead of `blog`, `news`, `tag`), same host only, bounded by `config('eveil.crawl.max_pages')`. A homepage alone rarely says what a product costs or who it is for.

- Plain HTTP only. No headless browser until the JS-render failure rate is measured.
- `RobotsPolicy` and `PageFetcher` are SINGLETONS: they hold the parsed robots.txt per host and the last-fetch timestamp the politeness delay is measured from. Resolving them per crawl re-fetches robots.txt and drops the throttle.
- Pages land in `crawled_pages`, the one instance-wide shared table (ADR-014). Public content only, keyed on `Url::normalize()`'d URL — the same normalisation the dedupe uses, so it lives in `App\Discovery\Url` and nowhere else.
- A missing `Content-Type` is accepted; only an explicitly non-HTML one is rejected. Small sites omit the header and refusing them would silently skip readable pages.
- Pages are passed around as `App\Discovery\ParsedPage`, not array shapes: array shapes are not covariant inside a `Collection` and fight PHPStan for no benefit.
- `Http::response('<html>…')` in tests sets NO Content-Type header — a fake that looks right can still be rejected by content sniffing. Remember it when writing crawler tests.

## What the first live discovery run taught (2026-08-11)
Four real Belgian friteries came back with usable fit reasons, so the no-purchased-database thesis holds. Four things nearly stopped it, all now covered by tests:

- **Overpass answers HTTP 406 to Guzzle's default User-Agent.** It asks that clients identify themselves. Without the `EveilBot` header the source returns nothing, forever.
- **Place names repeat across the world.** A probe on "Charleroi" without a country also returned a Subway in Pennsylvania. Every Overpass probe carries an ISO 3166-1 country, resolved first, with the town looked up inside it via `map_to_area`.
- **A dead source and an empty market look identical.** The first run reported "no candidate at all, the sources were wrong" while the truth was a 406 on every probe. Sources record their failures (`ReportsFailures`), the run stores them in `stats.source_failures`, and the command prints them. Never let a source fail silently.
- **PostgreSQL text columns reject NUL bytes and invalid UTF-8**, and real pages contain both — one mis-encoded restaurant site killed a whole run two thirds of the way through. `PageFetcher::storable()` strips them, and qualification is wrapped per candidate so one bad site can never cost the run everything already found.

Cost measured: $0.0025 per qualification on Haiku, below the $0.0035 estimate. Extraction on the cheap model comes in under estimate; generation on the planner comes in over. Size the OUTPUT when estimating a new action.

## Contact extraction: what the first live run actually returned (2026-08-11)
Four qualified Belgian friteries went in. Out came **phone numbers on every one and not a single published email address** — one site named a person ("Ali, Owner/Chef") with no address. Guessing `info@` recovered two of the four; both came back `risky` because the domains are catch-all, so acceptance proved nothing.

Read that as a finding about the segment, not a bug: **local micro-businesses publish a phone and a Facebook page, not an email.** Roughly half are email-reachable at all, and mostly through a guessed generic address. Any ICP of this shape should be expected to convert poorly into email leads — a dark kitchen, an agency or a SaaS will do far better. Say so to the user rather than reporting an empty run as a failure; `--guess-generic` is opt-in for exactly that reason.

Mechanics worth remembering:
- Port 25 is NOT blocked from the Sail container — the SMTP probe really ran and really detected catch-all domains. Do not assume the probe is decorative in dev.
- Only `invalid` blocks a send. Catch-all is `risky`, a refused probe is `unknown`, and both stay sendable (ADR-007).
- Guessed addresses are stored `inferred` and are only kept when the mail server accepts them — never guessed-and-stored blind.
- The phone is kept on `companies.facts` even when no email exists: for this segment it is often the only way in, and a later channel will want it.
