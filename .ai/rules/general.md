---
paths:
  - '**'
  - composer.json
---

# General

## What Eveil is
**North star (Clément, 2026-08-10, widened 2026-08-11): "I give the URL and the info about my product, and the app finds me clients" — directly, or through whoever already touches them (ADR-031).** One input, one output. Use it as the arbitration test on every feature: does this reduce what the user must supply, or increase clients found? If neither, it does not ship. Every required field is debt — acceptable as an intermediate step, never as the end state. The primary path is paste-URL → watch → approve, NOT a campaign builder; the step builder is the escape hatch, not the home screen.

Eveil = **the open-source alternative to lemlist**. Category: multichannel outreach sequencer with AI personalisation, deliverability and a unified inbox. Not a data provider, not a CRM, not marketing automation.

Positioning, stated precisely (Clément, 2026-08-10): the whole category — lemlist, Instantly, Smartlead, Saleshandy, Reply.io — is proprietary SaaS billed per seat and per mailbox, with prospect data hosted by the vendor. Self-hosting unlocks unlimited mailboxes at no cost (exactly what Instantly/Smartlead charge for), data sovereignty, and no per-seat billing.

Do NOT claim "no open-source lemlist alternative exists" — [Linki](https://github.com/moaljumaa/linki) was open sourced March 2026 and claims that exact slot. Clément tested it 2026-08-10 and rates it poor: LinkedIn-first, manual targeting, effectively a lead magnet for Opsily managed hosting. So the slot is claimed but not occupied. Still cite Linki honestly — claiming to be alone on a niche where a repo exists costs credibility for nothing. Eveil's defensible slot is **email-first + zero-config targeting derived from the product URL**.

Open questions live in a numbered register in §9 of the spec. It is currently EMPTY — A1-A8, B1-B5 and C1-C8 were all settled as ADR-010 to ADR-030 on 2026-08-10/11. Add any new question there with an identifier, and promote it to an ADR in §3 once settled.

Hierarchy: User → Organization (billable entity) → Project (one product/site to promote, e.g. Dricle, Sendboo) → leads/campaigns/email accounts, all scoped to project.

Two per-project AI roles: Website (crawls the site → knowledge base, plus site and acquisition suggestions, ADR-032) and Sales (derives target profiles, finds and qualifies companies, extracts contacts, runs outreach). A profile targets a customer OR a partner — whoever already touches the customer (ADR-031). One agent class per specialisation backs them, in `app/Ai/Agents/`, each its own settings key — there is no role taxonomy (ADR-026).

Ships in two editions from one codebase: free self-hosted (docker compose) and paid cloud. Scope lives in saas-outreach-tool-user-stories.md at repo root — read it before planning features.

## Stack: verified versions and traps
Installed: Laravel 13, Inertia v3 + Vue 3, Wayfinder, Pest 5, Larastan, Pint, Boost. PostgreSQL everywhere, tests included (ADR-010) — the SQLite the starter shipped with is gone. Redis for queues, cache and locks; Horizon still to be added (ADR-011).

## Local development runs on Laravel Sail
Settled 2026-08-11. `compose.yaml` at the repo root is the SAIL dev stack (`laravel.test` on PHP 8.5, `pgsql`, `redis`). Host ports are deliberately shifted — app 8080, Postgres 5442, Redis 6382 — because other local projects already hold 80/5432/6379.

Run PHP through Sail: `./vendor/bin/sail artisan …`, `sail composer lint`, `sail artisan test`. The host's default `php` is 8.3 and fails composer's platform check; Herd's 8.4 binary lives at `~/Library/Application Support/Herd/bin/php84` if a host-side command is unavoidable.

Run JS tooling on the HOST (`npm run dev`, `npm run lint:check`). `node_modules` is installed with macOS-arm64 binaries and mounted into the Linux container, so eslint and Vite fail inside Sail. That is why `composer ci:check` fails in the container while every PHP check passes — run the PHP checks in Sail and the JS checks on the host.

The self-hosted deployment compose promised by Epic 1 is a SEPARATE artifact from `compose.yaml`. Do not turn the Sail file into the shipped one.

Planned, verified on packagist/npm 2026-08-10:
- laravel/ai — pinned at 0.10.3. Do NOT wrap it: an earlier wrapper was deleted after review because the package already provides every hook it reinvented. See `.ai/rules/ai.md` — agents extend `EveilAgent` and are called directly.
- laravel/fortify — v1.37.x, stable.
- @nuxt/ui v4.10 — declares `@inertiajs/vue3: ^2 || ^3` as peer dep, so Inertia use is officially supported (not Nuxt-only). Tailwind v4 already installed, which Nuxt UI 4 needs.

Do not add deps without approval.

## Decisions: licence AGPL, edition split, v0 scope
Settled 2026-08-10 with Clément:
- Licence: AGPL-3.0. Anyone hosting a modified version must publish their code — blocks a competing cloud. Do not add code under an incompatible licence.
- One repo, two editions. Cloud-only code lives under `app/Cloud/`, registered conditionally by a ServiceProvider on `APP_EDITION=self|cloud`. No second repo, no separate build.
- v0 = single vertical slice: scrape site → knowledge base → AI lead discovery → AI sequence → SMTP send with daily cap → IMAP reply detection → auto-pause → unified inbox. Orgs, multi-user and LinkedIn are deferred to v1+ — this is BUILD ORDER, not an edition split: self-hosted gets organizations, invitations and access management too, in core (see ADR-025 below).
- Sending: user's own SMTP/IMAP only. No ESP relay (cold outreach through Postmark/SES gets the account banned).

## AGPL everywhere, free-outbound CLA, cloud dir holds billing only
ADR-025, settled 2026-08-11. One `LICENSE`, AGPL-3.0, the whole repo — `app/Cloud/` included. No separately-licensed directory, no feature withheld from self-hosted.

`app/Cloud/` is NOT a legal boundary, only a conditional-loading mechanism, and its scope is **billing and credit metering, nothing else**: Stripe, `credit_prices`, `credit_wallets`, `credit_transactions`, trial guards. Everything else lives in core — organizations, roles, invitations and per-project access included, so **self-hosted gets multi-user**. Cloud adds only managed hosting, billing, the supplied AI key, and support. Do not put a feature behind `app/Cloud/` thinking it is protected; it isn't, and it would break the "core stays free with no artificial limits" promise (story 10.3).

CLA is required, modelled on Postiz: a licence grant, never a copyright assignment (contributors keep their copyright), with the outbound restricted to licences that are both FSF-free and OSI-approved. So the project can relicense to another free licence but can never go proprietary, BSL, or fair-source — contractually ruling out the move that cost Redis, HashiCorp and MongoDB their communities.

Strategic corollary: the moat is hosting, brand and execution speed — not code. Postiz (AGPL-3.0, no `ee/`, cloud runs identical code, monetised on hosting alone) is the precedent being followed.

Before going public: write `ICLA.md`, `CCLA.md`, `CONTRIBUTING.md`, wire a CLA-check bot, and have a lawyer review it — this is the one project decision that cannot be undone.

## All blocking open questions are settled — ADR-010 to ADR-033
As of 2026-08-12 the §9 register in the spec is empty: tiers A, B and C are all decided as ADR-010 through ADR-030, plus ADR-031 (partner profiles), ADR-032 (acquisition recommendations) and ADR-033 (job-graph discovery, directories as a source) added as scope. Read §3 of `saas-outreach-tool-user-stories.md` before proposing anything architectural — the answer is probably already there, with its reasoning.

Two deadlines remain, non-blocking for development but not to be discovered the night before launch:
- Before opening the repo: write `ICLA.md`, `CCLA.md`, `CONTRIBUTING.md`, wire a CLA-check bot, have a lawyer review the licence and CLA (ADR-025).
- Before any public communication: pick the domain and run an EUIPO trademark search (ADR-030). The name stays "Eveil"; `eveil.com/.app/.io/.ai/.be` are taken, `eveil.dev/.email/.so`, `geteveil.com` and `useeveil.com` were free on 2026-08-11. Known and accepted downsides: the missing accent (French spells it Éveil), a saturated French keyword, and poor readability for English speakers.

When a new open question appears, add it to the §9 register with an identifier, and promote it to an ADR in §3 once settled.

## Story status lives in the spec, and it is your job to update it
Added 2026-08-12 because the ADR count was outrunning what exists. Every story in §7 of `saas-outreach-tool-user-stories.md` carries a marker — `✅` done and tested, `🟡` backend/CLI only with no UI, `⬜` not started — plus a per-epic rollup at the top of the section.

**Update the marker in the same commit as the code.** An unmarked or stale story is worse than none: the whole point is that §7 answers "what is left" without reading the codebase. When a story moves to `🟡`, say on the story itself what is missing. When the schema for a story does not exist yet, say so — several ADRs (031 partner ICPs, 032 recommendations, 033 directories) are decided in the spec but have no migration, and that gap is invisible from the ADR text alone.

Do not invent a second tracker. No TODO.md, no issues file, no task list in another document — they drift apart within a week and then nobody trusts either.

## Code comments never cite the spec, and examples stay domain-agnostic
Two rules for anything under `app/`, `config/`, `database/` or `tests/`, settled 2026-08-12.

**No `ADR-0XX`, no `Epic N`, no `story N.N` in code.** `saas-outreach-tool-user-stories.md` is our working document and goes away after the MVP; a public repo full of dangling references to a file nobody has is worse than no comment. Keep the REASONING, drop the citation — write "a leak between projects is the worst bug this app can ship", not "(ADR-003)". Same for roadmap framing: never "this arrives with Epic 1". If something is provisional, say what makes it provisional ("temporary while we validate the approach") — and check first whether it actually is. `eveil:agent-model` reads as scaffolding but is permanent: a settings screen will front the same values, and the command is still how you change a model over SSH on a self-hosted box.

**Examples in prompts and comments must not anchor on one industry.** The first real test was a food-ordering product, and restaurants, friteries and pizzerias leaked into agent instructions everywhere — which biases the model on every OTHER kind of business the app is for. Concrete examples are good and abstract ones teach nothing; the fix is to VARY them across sectors, not to remove them. An ICP example should span software, local services and industry; an Overpass tag list should cover offices, health, retail, trade and industry, and say the list is a starting point rather than the vocabulary. Place names in examples: prefer the neutral or globally recognisable over the local.

