# Eveil: decisions and reasoning

The durable "why" behind the product and the architecture. Not a task list — see
[GitHub Issues](https://github.com/Dricle/eveil/issues) for what's left to build, and
`.ai/rules/` for settled code-level conventions and traps. This file is for decisions
that would otherwise get re-argued or silently reversed: read it before proposing
anything that touches licensing, pricing, the self-hosted/cloud split, deliverability,
or the shape of discovery.

Do not cite this file by section number in code comments — if a comment needs the
reasoning, write the reasoning, not a pointer here.

## Vision and positioning

**North star**: *"I give the URL and the info about my product. The app finds me
clients — directly, or through whoever already touches them."* One input, one output.
Use it as the arbitration test on every feature: does this reduce what the user must
supply, or increase clients found? If neither, it doesn't ship. Every required field is
debt — acceptable as an intermediate step, never as the end state. The primary path is
paste-URL → watch → approve, not a campaign builder; the step builder is the escape
hatch, not the home screen.

**Category anchor**: the open-source alternative to lemlist. Multichannel outreach
sequencer with AI personalisation, deliverability, and a unified inbox — not a data
provider, not a CRM, not marketing automation. The whole category (lemlist, Instantly,
Smartlead, Saleshandy, Reply.io) is proprietary SaaS billed per seat and per mailbox,
with prospect data hosted by the vendor. Self-hosting unlocks: unlimited mailboxes at no
cost (exactly what Instantly/Smartlead charge for), data sovereignty, no per-seat
billing (organizations and multi-user live in core, not behind cloud), zero-config
targeting derived from the product URL, and end-to-end shared context (discovery,
qualification and personalisation all read the same knowledge base).

[Linki](https://github.com/moaljumaa/linki) already claims "open source lemlist
alternative" — cite it honestly rather than claiming the slot is empty, but it evaluates
poorly (LinkedIn-first, manual targeting, effectively a lead-gen funnel for managed
hosting). The slot is claimed, not occupied. Eveil's defensible claim: *"the only one
that's email-first and derives targeting from the product."*

**Personas**: solo-founder technical (self-hosted, wants operational in 15 minutes, no
third-party API key to sign up for), small growth team (cloud, wants multi-user and
managed hosting, fears opaque billing and unpredictable AI cost), instance superadmin
(self-hosted, wants to configure the AI provider and close registration from a screen,
not by editing files).

## Architecture

```
User ──< Organization (billable entity in cloud)
            └──< Project (one product/site to promote)
                    ├── Knowledge base (from site analysis)
                    ├── Target profile (derived, editable)
                    ├── Companies ──< Leads
                    ├── Email accounts
                    └── Campaigns ──< Steps ──< Variants
```

Everything under a project carries `project_id` behind a global scope. **A data leak
between projects is the worst bug this app can ship.**

Two per-project AI roles, one agent class per specialisation, no role taxonomy (grouping
by role puts unrelated jobs on one settings/pricing line):
- **Website**: URL (+ optional GitHub repo) → knowledge base, plus site and acquisition
  suggestions as a free by-product of the same call. Not an SEO audit tool.
- **Sales**: knowledge base + target profile → qualified companies, verified contacts,
  outreach sequences, handled replies.
- **Inbound** (later): target profile + knowledge base → anchored channel opportunities
  (Reddit, articles, X/Bluesky, LinkedIn), published per the autonomy setting; companies
  crossed along the way feed back into discovery.

A target profile can target a *customer* or a *partner* — whoever already touches the
customer (an accountant serving 50 restaurants is a partner profile with far better
reach than any one restaurant). Partner profiles carry `access_angle` (how this actor
touches the target) and `partnership_angle` (why the deal is good for them).

Discovery pipeline (5 stages, each independently testable): target-profile derivation →
company discovery (agent plans *where* to look, code executes at volume) → qualification
(fetch + small model → fit score + reason) → contact discovery (`/about`, `/team`, legal
mentions → names + roles → email pattern inference) → verification (MX, disposable
domain, catch-all, SMTP probe). The intelligence is in stage 2's *search strategy*, not
in scraping technique.

Discovery itself is a **graph of jobs, not an agentic tool loop** — a loop's cost grows
quadratically with depth (each step resends the whole history), a job graph stays flat.
A search result has two natures, not one: *entity* (one company) or *index* (a listing
page — pagesdor.be/friteries/namur is two hundred companies, not one). The model decides
where to look; plain code does the volume (`HarvestListing`: sitemap → JSON-LD → learned
CSS selectors → LLM extraction as last resort, since directories essentially never ship
usable JSON-LD in practice). A `directories` registry self-feeds from what discovery
actually finds productive, never hand-curated. One status flag
(`discovery_runs.status`) carries both budget exhaustion and cancellation; queued jobs
check it on pickup and self-delete.

Deliverability and GDPR are structural, not cosmetic — built *with* sending, never after:
- Opt-out is a sentence in the body ("reply STOP"), never a link or `List-Unsubscribe`
  header — the mail must stay indistinguishable from one typed by hand.
- "STOP" detection is a compliance mechanism, not a metric: it's the *only* opt-out
  channel. It errs toward suppressing: a false positive costs a lead, a false negative
  costs a complaint.
- Three-layer suppression list, checked before every send (see below).
- Hard bounce → automatic suppression; soft bounce → capped retries. Untreated bounces
  kill sender reputation in weeks.
- Provenance (`source`, `source_url`, `discovered_at`) stored per lead for internal
  audit, never injected into the mail itself — no generated legal text, no hosted notice
  page. Article 14 disclosure obligation sits on the user as data controller.
- Reply attribution by `Message-ID`/`In-Reply-To`, never by from-address. Auto-replies
  (out-of-office) are detected and never pause a campaign.

## Decisions

Settled. Don't reopen without a genuinely new fact.

**Licensing and repo shape**
- **AGPL-3.0, one repo, one `LICENSE`, `app/Cloud/` included.** Anyone hosting a
  modified version must publish their changes — blocks a competing cloud fork while
  staying genuinely open source (OSI-recognized, unlike fair-source). Precedent: Postiz
  (AGPL-3.0, no `ee/` directory, cloud runs identical code, monetized on hosting alone).
  Two-repo models (Sentry's OSS-public + private-overlay) cost a fortune in maintenance;
  GitLab, Chatwoot, n8n and Plausible all run mono-repo with a licensed folder instead.
- **`app/Cloud/` is a conditional-loading mechanism, not a legal boundary.** Its scope is
  billing and credit metering *only*: Stripe, `credit_prices`, `credit_transactions`,
  trial guards. Everything else — organizations, roles, invitations, per-project access —
  lives in core, so **self-hosted gets multi-user for free**. Cloud adds only managed
  hosting, billing, the supplied AI key, and support. Never put a feature behind
  `app/Cloud/` thinking it's protected — it isn't, and doing so breaks the "core stays
  free with no artificial limits" promise.
- **CLA required, outbound bounded to free software.** A *license* grant, never a
  copyright assignment — contributors keep their copyright. The project can relicense to
  any licence that is both FSF-free and OSI-approved, but can never go proprietary, BSL,
  or fair-source. This is the compromise that keeps flexibility *inside* open source
  while contractually ruling out the move that cost Redis, HashiCorp and MongoDB their
  communities. Before going public: `ICLA.md`, `CCLA.md`, `CONTRIBUTING.md`, a CLA-check
  bot, and a lawyer's review — the one decision here that can't be undone.
- **Three separate permission scopes, never merged into one `role` column**: instance
  (`users.is_super_admin`, boolean), organization (`organization_user.role`: owner/
  admin/member), project (`project_user` pivot, plain access grant, no role of its own).
  Self-hosted still gets an implicit organization at setup — one code path, never two.

**Product shape**
- **Agents are queued jobs, not daemons.** A prompt + a toolset + a job, nothing
  persistent. Every invocation writes an `agent_runs` row (tokens, cost inputs, duration,
  status, error) — simultaneously the debug log, the analysis history, and the billing
  meter. Every run carries a hard budget (max tokens/pages/leads) and stops on it.
- **Autonomy is a three-notch setting, per project**: *Supervised* (human approval at
  every stage — first project, cautious user, sensitive sector), *Semi-auto* (default —
  approve the target profile and a sequence sample once, then autopilot with escalation
  on anomaly), *Autonomous* (send from the URL alone). The human-escalation conditions
  (bounce-rate threshold, any spam complaint, abnormal negative-reply rate, an auth
  error) are common to semi-auto and autonomous and cut sending regardless of setting —
  autonomous removes *a priori* checkpoints, never the circuit breakers.
- **No mailbox warm-up, anywhere, ever.** Warm-up serves fresh domains going to high
  volume; the target persona sends ~30/day from a real, years-old mailbox that's already
  "warm." Local warm-up between a user's own mailboxes builds no reputation (filters
  watch engagement from strangers, not a closed loop) — it's theatre. Shared warm-up
  networks are an increasingly detected, increasingly negative signal. What actually
  drives deliverability: ramp-up on a new account, daily caps, pacing (never bursty),
  bounce suppression, pre-send verification, clean opt-out, and one-by-one
  personalization. Documented gap versus lemlist/Instantly, accepted deliberately.
- **No open/click tracking.** No pixel, no link rewriting. Apple Mail Privacy Protection
  and Gmail's proxy make open counts fiction; a pixel also costs inbox placement, and
  planting one without consent on cold email is hard to defend in Europe. The tracked
  metric is the *positive* reply — never the raw reply rate, which counts "no thanks" and
  out-of-office equally with real interest. `reply.handle`'s classification writes
  `messages.classification` as a side effect of *acting* (pause, reschedule, ask for the
  right contact, suppress) — it is not a separate classifier feeding a router; that would
  double-pass every mail and couldn't ask a clarifying question. Manual gain-marking
  (a "client signed" flag) unlocks true cost-per-client, since the app only ever sees
  replies, never a signed contract.
- **SMTP/IMAP only, no OAuth**, in either edition. The datacenter-IP-gets-blocked
  worry that originally motivated looking at OAuth was simply false for IMAP/SMTP client
  connections. The real, accepted risk: Google Workspace admins can disable app
  passwords org-wide at any time, and Microsoft 365 is walking SMTP AUTH toward default-
  off (already default-off on new tenants, existing tenants follow from ~2027).
  Mitigation is diagnostic, not evasive: a failed connection test names the *exact* cause
  ("your Workspace admin disabled app passwords," "SMTP AUTH is off on your M365
  tenant, here's how to re-enable it") rather than a generic auth failure.
- **Sender-side rejections are not recipient bounces.** A mail server refusing to relay
  because the *From* address isn't verified (e.g. "553 Sender is not allowed to relay
  emails") must never be read as a dead recipient address — the address gets deleted
  forever, the mailbox gets punished, and the actual fix is a one-click setting change.
  Sender-side refusals are checked before recipient bounce codes.
- **Language detected per company, not per project.** UI stays English-only in v0 (real
  i18n has a real cost, zero effect on lead quality); search queries and outbound emails
  follow the *market's* and the *prospect's* language respectively — writing English to
  a Belgian SME kills response rate. Belgium is the case that breaks a project-level
  toggle: French, Dutch and English in the same country, sometimes the same city.
  Detected once during qualification crawl (already-fetched page, so free): page `lang`
  attribute → TLD/geography → project default. Adds no extra AI call — personalization
  is already one LLM call per lead, writing in a different language is just another
  instruction; a hand-written template hitting a lead in another language gets
  translated on send, cached by (template, language) pair, and shown translated in
  preview so nothing goes out unseen.
- **Discovery diagnoses before it widens.** "Nothing found" hides at least four distinct
  failures — narrow target profile, wrong source, systematically-low fit (the profile
  itself is wrong; widening here is the worst outcome, since the agent then produces
  off-target leads that get contacted and generate complaints), or a qualification
  problem rather than a targeting one. Market exhaustion is a *result*, not a failure —
  telling a user their market is 40 companies is more useful than scraping noise to fill
  a quota, and no competitor says this because they sell by volume. Widening moves one
  axis at a time (geography → size → adjacent sectors → job titles), two notches max,
  every relaxation logged and shown, and widening attempts spend the *same* run's budget
  rather than opening a new one.
- **Retention: automatic purge, CNIL-referenced defaults, operator-tunable with a
  floor.** Contacted lead: 3 years after last contact. Discovered-never-contacted lead:
  6 months. `agent_runs` payloads (input/output, contain names/emails): 90 days.
  `agent_runs` metrics (tokens, duration, status — feed billing): unlimited. Erasure is
  carried by the lead row itself, scoped to the project (not a separate `erasures`
  table) — the row survives *emptied* (name, contact fields, source URL, and any sent
  message bodies that quote the address all cleared) rather than deleted, because a
  hard-deleted row would just get rediscovered and recontacted by the next run. What
  survives: `email_hash` (one-way, consulted at both discovery and send time to refuse
  the person forever) and `erased_at`. Erring toward over-erasure is never a violation;
  under-erasure is.

**Discovery and sourcing**
- **Sourcing needs no third-party API key**: SearXNG (self-hosted meta-search, free, no
  key) plus OpenStreetMap Overpass and the GitHub API (both free, no key). Runs fully
  self-hosted with zero external subscription — a real onboarding argument. Accepted
  risk: SearXNG can get rate-limited; a Brave/Serper driver can slot behind the same
  interface if that becomes blocking. Official business registries (Belgian BCE/KBO,
  French SIRENE, UK Companies House) are a further free, exhaustive, zero-SEO-bias
  source layer, planned but not yet built — they carry no email or site, so they feed
  enrichment, not sending, directly.
- **Home-built email verification**: MX check, then an SMTP `RCPT TO` probe with no
  actual send. Catch-all domains are mandatorily flagged `risky`, never `valid`; a probe
  blocked by Gmail/Outlook resolves to `unknown`, never `invalid`. A third-party
  verifier can slot in later as a driver.
- **Directory/listing pages are a source, not noise to blacklist.** A directory URL
  isn't one company, it's potentially hundreds, and it's often the *only* place a
  siteless company publishes a contact email — blacklisting the domain throws away the
  answer along with the junk. A result is *sorted* (entity vs. index), never simply
  filtered. The directory registry self-populates from what discovery actually finds
  productive, tracking *why* a host failed (`blocked`/`js_only`/`jsonld`/`llm`) so a
  dead end is never re-paid for.
- **Per-project data, shared page cache.** Companies and leads stay siloed per project —
  what's expensive in discovery is the LLM qualification, not the HTTP fetch, and fit
  score is inherently profile-specific (the same company scores 90 for one product, 20
  for another), so sharing company data would only save the profile-independent half at
  the cost of a join everywhere and an arbitration problem the moment two projects
  disagree on facts about the same company. A raw-page cache (URL → content, public
  content only, never anything behind a login) is shared at the instance level and is
  safe even in cloud, since it holds no client data.
- **Fit score lives on a join table, never on the company row**: `companies` (dedup'd
  firmographic facts) × `company_target_evaluations` (company × target profile → fit
  score + reason). A lead found by two profiles is never contacted twice — it belongs to
  at most one active campaign per project, and the second profile just records the
  overlap.
- **No PostgreSQL alternative, anywhere, including tests.** Discovery runs write in
  parallel from multiple workers for minutes at a stretch; SQLite accepts one writer at
  a time even in WAL mode. The schema is JSON-heavy and needs JSONB's indexability,
  partial unique indexes, and native full-text search. Testing against SQLite while
  running Postgres in production diverges on exactly what this schema leans on hardest —
  tests pass, prod breaks.
- **Redis + Horizon for queues, cache and locks.** This app is fundamentally a job
  engine: queues run at genuinely different rhythms (discovery can saturate workers but
  is bounded by its own run budget; crawl is throttled per domain; sending is
  deliberately slow and spread across the day, never bursty), needs atomic per-domain
  rate limiting during crawls, and needs distributed locks so two workers never touch
  the same mailbox or domain at once. A silently-dead discovery run is this project's
  single most likely bug class, and `agent_runs` alone can't explain *why* a job vanished
  — Horizon's observability is load-bearing, not a nice-to-have.
- **Credentials get their own encryption key, separate from `APP_KEY`.** `APP_KEY` also
  encrypts cookies and sessions, and best practice says rotate it after any leak — but
  rotating a key shared with mailbox credentials would permanently break every connected
  mailbox, so in practice nobody would ever rotate it. `CREDENTIALS_KEY` decouples that
  at the cost of one env var. Mandatory guardrails regardless: an encrypted canary
  checked at boot (refuse to start with a clear error if it won't decrypt, not a
  `DecryptException` three days deep in a job), rotation via `APP_PREVIOUS_KEYS`-style
  dual-key decryption, a re-encryption command, and an explicit warning that a DB dump
  without its matching `.env` is worthless.
- **Three-layer suppression list**, scoped to match what would leak across tenants if
  scoped wrong: opt-outs/STOP replies scope to the **project** (an agency organization
  prospects for unrelated clients — a prospect opted out of one client's product isn't
  necessarily opted out of another's); hard bounces scope to the **email account** (an
  address can bounce from one sender and not another); toxic addresses (spam traps,
  burned domains, disposable domains) scope to the **whole instance**, fed only by
  public lists and our own detection — *never* by a client's prospect behavior, since
  that would leak who is prospecting whom across tenants. Two mandatory safety valves on
  the project-scoped opt-out layer: a spam complaint (as opposed to a plain unsubscribe)
  always escalates to the *entire organization* regardless of which project it came
  from; the same address replying STOP on two projects of the same organization
  auto-escalates the opt-out to the organization level.

**Billing (cloud only)**
- **Credits in cloud, the operator's own key in self-hosted.** Self-hosted: zero credit
  tracking, zero billing code, zero counting — `agent_runs` still exists for debug and
  history, just never the ledger. Cloud: every action debits a credit count from an
  operator-adjustable table, deployed without a redeploy; the user only ever sees
  credits, never tokens, never a model name. The AI provider/model stays configurable
  and swappable in both editions — credits decouple the price shown from the real cost,
  so changing the LLM moves the operator's margin, never the customer's price.
- **Pay-as-you-go, no plans, no subscription.** A customer tops up whatever amount they
  choose, converted to credits at one flat published rate
  (`billing.credits_per_dollar`). Calibration anchor: 1000 credits ≈ $1 of real AI cost,
  target margin 3×. If the provider's price moves, the fix is a new `credit_prices` row
  with a later `effective_from` — that's a *separate* lever from the $→credits rate,
  which stays stable for the customer. **Credits never expire**, whether from the trial
  grant or a top-up — a deliberate liability (unspent credits bought at an old rate),
  accepted for the promise "a credit you paid for is always yours," same posture as
  OpenAI/Anthropic API credits.
- **Bill per unit of work, never per "run."** A run evaluating 400 companies costs ten
  times one evaluating 40 — a flat per-run fee would lose money on the big ones and
  overcharge the small ones. The credit price grid is versioned (`effective_from`,
  never edited in place) so a rate change never re-prices history, and each transaction
  freezes the rate actually charged at debit time. A run the app aborts on its own error
  is never billed; a run the user interrupts bills only the work actually produced. In
  cloud, the run's own hard budget (see Product shape, above) *is* the credit
  reservation — one mechanism, not two.
- **Auto top-up is the pay-as-you-go answer to a subscription's auto-renewal**: a saved
  card, a threshold, and an amount — under the threshold, an off-session charge recharges
  without a confirmation prompt, since nobody is at the keyboard when the balance crosses
  the line. Checked after every debit, never inside the debit's own transaction (a
  Stripe call is a network round-trip and must never hold a DB lock). The card is saved
  via a Stripe-hosted Checkout Session in `setup` mode — no form of ours, no Stripe.js to
  load — and it's the `checkout.session.completed` webhook, never the browser's return
  trip, that actually records it.
- **Trial**: ~5,000 credits at signup, enough for one full campaign through to replies —
  the self-hosted edition is free, so a trial that stops short of the first reply
  convinces nobody. This is a real abuse vector on a product whose whole job is
  extracting emails (5,000 credits ≈ 100 qualified leads), so it ships with mandatory
  guards: verified email, one project only, a cap on leads *discovered* (a separate axis
  from credit spend), and no CSV export before a first payment — a trial user can see and
  email their leads, but never walks away with the file.
- **Invoices and payment methods: Stripe's own hosted surfaces, not custom screens.**
  The Billing Portal for invoice history/download; a `setup`-mode Checkout Session for
  saving a card. Tax ID collection (VAT number + billing address) rides the same
  mechanism — collected and stored on the Stripe customer, never by us, and never with
  automatic tax calculation (Stripe Tax) layered on: just collection for the invoice.

## Cut line (what's still ahead)

v0 (single-project outreach loop: knowledge base → target profile → discovery →
qualified companies → verified contacts → AI sequence → capped SMTP send → IMAP reply
detection → auto-pause → unified inbox) and the "cloud edition" backbone (organizations,
roles, invitations, per-project access, pay-as-you-go billing) are both built. What's
still ahead, tracked as [GitHub Issues](https://github.com/Dricle/eveil/issues):

- The **inbound half**: agents publishing to Reddit, SEO articles, X/Bluesky, LinkedIn —
  driven by the target profile, not a content calendar (that's the one thing no
  competitor doing "AI CMO" content generation can copy). Blocked until the outbound
  loop above is airtight: inbound is the *cheap* half of the problem (an LLM drafts, a
  human publishes — no deliverability, no address verification, no IMAP, no consequence
  for a mediocre draft), and shipping it before outbound would produce a worse clone of
  an existing competitor that additionally can't send an email.
- LinkedIn outbound (its own container, real anti-detection cost — a product in itself).
- A public API, an MCP server, CRM webhooks.
- Third-party lead-provider drivers (Apollo, Hunter), third-party email verification
  drivers, official business registries as a discovery source, headless-browser
  rendering for JS-only directories.
- A handful of smaller v1 items: multi-project dashboard, linking a GitHub repo for
  deeper analysis, A/B testing of sequence variants, conditional template blocks, mailbox
  ramp-up and rotation, OAuth for Gmail/Microsoft.

## Explicitly out of scope

Not to be built, even when the temptation shows up:

- **A full SEO audit tool.** The Website agent's suggestions are a free by-product of
  the knowledge-base call, nothing more. Lighthouse scores, a design guide, UGC video, an
  agent that opens pull requests on the user's repo — all out, even once inbound ships:
  these are sales surfaces, not clients found.
- **A proprietary contact database.** Discovery runs live; Eveil doesn't accumulate a
  database of hundreds of millions of contacts to resell, on purpose — live discovery
  finds fresh, long-tail targets (local shops, recent companies, niche directories) that
  a purchased, stale, anglo-centric database misses. Someone who actually wants to
  filter 275M rows should be pointed elsewhere, not disappointed here.
- **An ESP relay** (Postmark, SES, etc.) for sending.
- **Open/click tracking as the headline metric.**
- **A CRM.** Integrate with one; never become one.
- **A task manager.** Acquisition recommendations carry a state (proposed → done/
  archived) that the agent updates from conversation — the moment anything needs manual
  ranking, assignment, or due dates by hand, that's a task manager and it's out.
- **A strategy document generator.** A recommendation with no verifiable evidence and no
  execution behind it is generic advice. Eveil finds, contacts, and publishes; it does
  not write plans to be read. (Inbound channel agents are the one exception, precisely
  *because* they publish — the day one of them produces only a document to read instead,
  it falls back into this line.)
