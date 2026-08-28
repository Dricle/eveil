---
paths:
  - '**'
  - composer.json
---

# General

## What Eveil is
**North star: "I give the URL and the info about my product, and the app finds me clients". Directly, or through whoever already touches them (ADR-031).** One input, one output. Use it as the arbitration test on every feature: does this reduce what the user must supply, or increase clients found? If neither, it does not ship. Every required field is debt: acceptable as an intermediate step, never as the end state. The primary path is paste-URL → watch → approve, NOT a campaign builder; the step builder is the escape hatch, not the home screen.

Eveil = **the open-source alternative to lemlist**. Category: multichannel outreach sequencer with AI personalisation, deliverability and a unified inbox. Not a data provider, not a CRM, not marketing automation.

Positioning, stated precisely: the whole category. Lemlist, Instantly, Smartlead, Saleshandy, Reply.io: is proprietary SaaS billed per seat and per mailbox, with prospect data hosted by the vendor. Self-hosting unlocks unlimited mailboxes at no cost (exactly what Instantly/Smartlead charge for), data sovereignty, and no per-seat billing.

Do NOT claim "no open-source lemlist alternative exists": [Linki](https://github.com/moaljumaa/linki) is open source and claims that exact slot. It rates poor on evaluation: LinkedIn-first, manual targeting, effectively a lead magnet for Opsily managed hosting. So the slot is claimed but not occupied. Still cite Linki honestly: claiming to be alone on a niche where a repo exists costs credibility for nothing. Eveil's defensible slot is **email-first + zero-config targeting derived from the product URL**.

Hierarchy: User → Organization (billable entity) → Project (one product/site to promote, e.g. Dricle, Sendboo) → leads/campaigns/email accounts, all scoped to project.

Two per-project AI roles: Website (crawls the site → knowledge base, plus site and acquisition suggestions) and Sales (derives target profiles, finds and qualifies companies, extracts contacts, runs outreach). A profile targets a customer OR a partner: whoever already touches the customer. One agent class per specialisation backs them, in `app/Ai/Agents/`, each its own settings key: there is no role taxonomy.

Ships in two editions from one codebase: free self-hosted (docker compose) and paid cloud. `GUIDELINES.md` at repo root holds the durable product/architecture decisions and the reasoning behind them: read it before proposing anything that touches licensing, pricing, the self-hosted/cloud split, deliverability, or the shape of discovery.

## Stack: verified versions and traps
Installed: Laravel 13, Inertia v3 + Vue 3, Wayfinder, Pest 5, Larastan, Pint, Boost. PostgreSQL everywhere, tests included (ADR-010): the SQLite the starter shipped with is gone. Redis for queues, cache and locks, with `laravel/horizon` running the workers (ADR-011): see `.ai/rules/jobs.md`.

## Local development runs on Laravel Sail
`compose.yaml` at the repo root is the SAIL dev stack (`laravel.test` on PHP 8.5, `pgsql`, `redis`). Host ports are deliberately shifted: app 8080, Postgres 5442, Redis 6382, because other local projects already hold 80/5432/6379.

Run PHP through Sail: `./vendor/bin/sail artisan …`, `sail composer lint`, `sail artisan test`. The host's default `php` is 8.3 and fails composer's platform check; Herd's 8.4 binary lives at `~/Library/Application Support/Herd/bin/php84` if a host-side command is unavoidable.

Run JS tooling on the HOST, with **Yarn 4** (`yarn dev`, `yarn lint:check`). Corepack picks the version from `packageManager` in `package.json`, and there is no `package-lock.json` any more. The host `php` must be 8.4+: `yarn dev` and `yarn build` shell out to `php artisan wayfinder:generate`, and on an older binary the build dies with `RolldownError: Error generating types` and every page then 500s on a missing Vite manifest. `node_modules` is installed with macOS-arm64 binaries and mounted into the Linux container, so eslint and Vite fail inside Sail. That is why `composer ci:check` fails in the container while every PHP check passes. Run the PHP checks in Sail and the JS checks on the host.

The self-hosted deployment compose promised by Epic 1 is a SEPARATE artifact from `compose.yaml`. Do not turn the Sail file into the shipped one.

Also installed: laravel/fortify 1.38 and @nuxt/ui 4.10. See "Auth is Fortify" and "The app lives under /app" below.

Planned:
- laravel/ai. Pinned at 0.10.3. Do NOT wrap it: the package already provides every hook a wrapper would reinvent. See `.ai/rules/ai.md`: agents extend `EveilAgent` and are called directly.

Do not add deps without approval.

## Auth is Fortify: 2FA, reset, and a setup screen for the first account
`laravel/fortify` owns login, logout, password reset, password confirmation and TOTP two-factor. It is headless: the screens are Inertia pages wired in `FortifyServiceProvider::registerViews()`, which is where you add or change one. Never hand-roll an auth route beside it.

- **Sign-ups are open by default and closed with `REGISTRATION_ENABLED=false`.** It is one line in `config/fortify.php`'s features array: closed means Fortify never registers the routes, so `/app/register` is a genuine 404 with no code of ours involved.
  The catch to respect: **no page may import `@/routes/register`**, because Wayfinder generates from the route table and emits that module only where sign-ups are open. A build on a closed instance would fail on an import that exists on someone else's laptop. So `HandleInertiaRequests` shares `registerUrl` (the URL, or `null`) for the login page's link, and the register page takes its form `action` as a prop from `Fortify::registerView()`. Same rule for any future route behind a feature flag.
  In tests the flag is on (`phpunit.xml`), since routes are registered at boot; the closed case is covered by a test that sets `$_SERVER['REGISTRATION_ENABLED']` and calls `refreshApplication()`.
- **The first account comes from `/app/setup`** (`Auth\SetupController`), not from registration: it creates the super admin AND the organization they own, because self-hosted runs the same organization code path as cloud. `Fortify::loginView()` redirects there while `users` is empty, so a fresh instance never shows a login form nobody can pass.
- **Every account is created by `App\Actions\CreateAccount`**: setup and registration both go through it, so "a user always owns an organization" holds in one place. A user without one can own nothing and dies on the first project they create. It also de-duplicates the organization slug: two companies picking the same name is ordinary, and the column is unique.
- **Passkeys are deliberately not enabled**, though `laravel/passkeys` arrives as a Fortify dependency. The feature and its published migration were removed; turn it on only when someone asks for it.
- **Account management lives at `/app/account`**, one screen per concern behind a sidebar (`AccountLayout.vue`): profile, password, two-factor, delete. Controllers are in `app/Http/Controllers/Account/`; profile and password need no controller at all (`Route::inertia`) because their forms post straight to Fortify's update routes. Those validate into NAMED error bags, so the page must pass `error-bag="updateProfileInformation"` / `"updatePassword"` or the errors never appear.
- **2FA enrolment is server-rendered.** `Account\TwoFactorController` passes the QR SVG and recovery codes as props and the page posts back to Fortify's own routes, so no page has to assemble state from Fortify's JSON endpoints.
- **Deleting an account also deletes the organizations it empties** (`App\Actions\DeleteAccount`), and their projects with them through the foreign keys. An organization with no members left is unreachable: nothing can grant access to it again, so leaving it behind is orphaned data, not caution. Organizations with other members survive: one person leaving a team is not the team being deleted. The password is re-asked for on this screen alone, because it is the one action nobody can undo.
- **Fortify caches the code just used**, so replaying the same OTP inside its window fails. In tests, confirm enrolment with an OTP and pass the CHALLENGE with a recovery code: otherwise the second step fails for a reason that looks nothing like the real cause.
- The app's own mail (resets, later invitations) is plain `MAIL_*` config, and is NOT the outreach sender: campaigns go through the email accounts a user connects. The Sail stack ships Mailpit; its dashboard is on `http://localhost:8035` (host ports are shifted here, as everywhere in `compose.yaml`).

## The app lives under /app, the public site is Blade
Two front ends, on purpose:
- `routes/web.php`: the public site, plain Blade under `resources/views/marketing/`. No Inertia, no Vue. It is served only when `APP_EDITION=cloud` (`config('eveil.edition')`); a self-hosted instance has nothing to sell, so `/` redirects to `/app`.
- `routes/app.php`: the Inertia + Vue application, mounted at the `/app` prefix by `bootstrap/app.php`. Fortify's `prefix` in `config/fortify.php` is set to `app` to match, so every auth URL sits under the same prefix. Adding a screen means adding it here, not in `web.php`.

**You are always inside a project.** `AppLayout.vue`'s sidebar opens with the project switcher, which is also where a project is created: there is no project list in the nav, because every screen below the dashboard belongs to one project. The dashboard is that project's dashboard. Project-level configuration lives under Settings (`SettingsLayout.vue`), alongside the instance settings still to come. See `.ai/rules/routes.md` for the session mechanics.

**The application is full width, always.** Every authenticated page composes `AppLayout.vue`: a `USidebar` (`collapsible="icon"`) beside a scrolling content column, and never wraps itself in an `mx-auto max-w-*` container. A page that centres itself in a column both fights the shell and disagrees with the page next to it. Pages fill the header bar through the layout's `#header` slot. `AccountLayout.vue` is the pattern for a sub-section: a fixed-width `aside` of links plus the content, inside `AppLayout`.

`AppLayout` sizes itself with `h-screen`, not the `flex-1` the Nuxt UI docs example uses: that example sits inside their own page frame, and this layout IS the frame.

Two deliberate exceptions, and only these: the marketing Blade page, where a reading column is right, and `AuthCard.vue`, which centres one card on an otherwise empty screen.

Nuxt UI 4 supplies the components. Three things it needs, already wired:
- `ui({ router: 'inertia' })` in `vite.config.ts`. Without the option its `ULink` imports `vue-router`, which this app does not have, and the build dies on `"RouterLink" is not exported`. The plugin also registers `@tailwindcss/vite` itself: do not add that plugin again.
- `@import '@nuxt/ui'` in `resources/css/app.css`.
- `@iconify-json/lucide` installed as a dev dependency, so icon names like `i-lucide-user` are bundled at build time. Without it Nuxt UI fetches each icon from the Iconify API at runtime: fine on a laptop, wrong for a self-hosted instance that may have no outbound internet. Stay on the `lucide` set unless you also install the collection you reach for.
- `app.use(ui)` through `withApp`, plus `layout: () => RootLayout` so every page renders inside `<UApp>` (toasts, overlays and tooltips need it). Components auto-import, so `<UButton>` and friends need no import line.

Theme: Vue mode has no `app.config.ts`, so what the Nuxt UI theme builder puts there goes in the plugin's `ui` option instead. Currently `colors: { primary: 'cyan', neutral: 'neutral' }`. The font is Raleway, declared twice on purpose: `bunny('Raleway')` in `vite.config.ts` fetches it, `--font-sans` in `resources/css/app.css` uses it. Do not go looking for `--ui-color-primary-*` in the built CSS: Nuxt UI injects the palettes at runtime from a Vue plugin, so they only ever exist in the bundle and in the live DOM.

## Decisions: licence AGPL, edition split, scope
- Licence: AGPL-3.0. Anyone hosting a modified version must publish their code: blocks a competing cloud. Do not add code under an incompatible licence.
- One repo, two editions. Cloud-only code lives under `app/Cloud/`, registered conditionally by a ServiceProvider on `APP_EDITION=self|cloud`. No second repo, no separate build.
- Sending: user's own SMTP/IMAP only. No ESP relay (cold outreach through Postmark/SES gets the account banned).
- Full reasoning behind every product/architecture decision - licensing, pricing, autonomy levels, deliverability, discovery's job-graph shape, and more - lives in `GUIDELINES.md` at repo root. Read it before proposing anything that touches those areas: the answer, and the reasoning behind it, is probably already there.

## AGPL everywhere, free-outbound CLA, cloud dir holds billing only
One `LICENSE`, AGPL-3.0, the whole repo: `app/Cloud/` included. No separately-licensed directory, no feature withheld from self-hosted.

`app/Cloud/` is NOT a legal boundary, only a conditional-loading mechanism, and its scope is **billing and credit metering, nothing else**: Stripe, `credit_prices`, `credit_transactions`, trial guards. Everything else lives in core: organizations, roles, invitations and per-project access included, so **self-hosted gets multi-user**. Cloud adds only managed hosting, billing, the supplied AI key, and support. Do not put a feature behind `app/Cloud/` thinking it is protected; it isn't, and it would break the "core stays free with no artificial limits" promise.

The credit balance itself is `organizations.credits_balance`, a plain column on the CORE `organizations` table (alongside `stripe_id`/`pm_type`/`auto_topup_*`) - not a `credit_wallets` table under `app/Cloud/`. It is a cloud-only VALUE, always zero and never written to on self-hosted, but the column lives with the rest of the model's billing fields rather than behind a one-to-one relation to a table holding a single integer.

CLA is required, modelled on Postiz: a licence grant, never a copyright assignment (contributors keep their copyright), with the outbound restricted to licences that are both FSF-free and OSI-approved. So the project can relicense to another free licence but can never go proprietary, BSL, or fair-source: contractually ruling out the move that cost Redis, HashiCorp and MongoDB their communities.

Strategic corollary: the moat is hosting, brand and execution speed. Not code. Postiz (AGPL-3.0, no `ee/`, cloud runs identical code, monetised on hosting alone) is the precedent being followed.

Before going public: write `ICLA.md`, `CCLA.md`, `CONTRIBUTING.md`, wire a CLA-check bot, and have a lawyer review it. This is the one project decision that cannot be undone. Also before any public communication: pick a domain and run an EUIPO trademark search - the name stays "Eveil", `.com/.app/.io/.ai/.be` are taken, `.dev/.email/.so`, `geteveil.com` and `useeveil.com` are the candidates.

## GitHub Issues is the tracker; `GUIDELINES.md` is the reasoning
[github.com/Dricle/eveil/issues](https://github.com/Dricle/eveil/issues) tracks what's left to build - one issue per feature, staged with milestones (`v1`, `v2: inbound`, `later`) and themed with labels, not a file in the repo. It replaced a root-level `TODO.md` once the two started drifting apart from the code; don't recreate that file or a third parallel list.

`GUIDELINES.md` holds the durable "why" - product vision, positioning, and every settled architectural/business decision with its reasoning. It is not a task list and does not get a checkbox for each open issue; when a new open question gets settled, its reasoning goes into `GUIDELINES.md`, and any GitHub issue it resolves gets closed with a reference to the commit, not to a section number.

## Code comments never cite an issue number, and examples stay domain-agnostic
Two rules for anything under `app/`, `config/`, `database/` or `tests/`.

**No issue number, no "Epic N", no "story N.N" in code.** A public repo full of references to a tracker entry nobody browsing the code has open is worse than no comment. Keep the REASONING, drop the citation: write "a leak between projects is the worst bug this app can ship", not "(#42)" or "(ADR-003)". Same for roadmap framing: never "this arrives with Epic 1". If something is provisional, say what makes it provisional ("temporary while we validate the approach"), and check first whether it actually is. `eveil:agent-model` reads as scaffolding but is permanent: a settings screen will front the same values, and the command is still how you change a model over SSH on a self-hosted box.

**Examples in prompts and comments must not anchor on one industry.** Whatever product is being tested at the time leaks into agent instructions: a food-ordering project fills them with restaurants, friteries and pizzerias. Which biases the model on every OTHER kind of business the app is for. Concrete examples are good and abstract ones teach nothing; the fix is to VARY them across sectors, not to remove them. An target profile example should span software, local services and industry; an Overpass tag list should cover offices, health, retail, trade and industry, and say the list is a starting point rather than the vocabulary. Place names in examples: prefer the neutral or globally recognisable over the local.

## Interfaces carry the `Interface` suffix
`DiscoverySourceInterface`, not `DiscoverySource`. The suffix says at the import line whether you are looking at a contract or a class, which matters most where a concrete implementation would otherwise read identically: `OverpassSource implements DiscoverySourceInterface` is unambiguous in a way `implements DiscoverySource` is not.

Applies to interfaces only. Abstract classes keep their plain name (`EveilAgent`), and so do traits. Third-party contracts keep whatever their package calls them: `Laravel\Ai\Contracts\Agent` is imported as-is, never aliased to match our convention.

## The database is the only source for settings; config is deployment only
`config/eveil.php` holds ONLY what an env file sets and no screen should: service URLs, HTTP timeouts, the user agent, the max-bytes safety limit, the SMTP probe's envelope sender. It must never mirror a tunable value as a fallback under the `settings` table: that gives two places to look and a merge to reason about on every read.

Everything that is a product decision: per-agent model mapping, pricing, discovery budgets, crawl limits, verification toggles, host-registry TTL. Lives in `settings`, seeded by `2026_08_11_100006_seed_default_settings`, read through `App\Support\Settings`.

- **A migration, not a seeder.** Seeders are optional and a forgotten one leaves the app with no values: zero pages crawled, a zero-millisecond politeness delay. That fails silently and looks like a different bug. Migrations always run.
- **`Settings::int()` / `bool()` / `array()` throw when a key is missing.** Casting null gives 0, and 0 is a plausible-looking catastrophe. A missing setting is a bug and should say so.
- **Merging moved from read to write.** `AgentSettings::save()` merges into the stored row, so changing only the model does not drop the timeout that keeps a thinking model off the 60s HTTP default.
- **`--reset` lands on a conservative default, NOT on what the install shipped with.** Restoring the seeded value would mean keeping a second copy of it in code, which is exactly the duplication this design avoids. The command prints what it landed on.
- **Path heuristics are not settings.** `CONTACT_PATHS` and `PRIORITY_PATHS` are consts on `FindContacts` and `SiteCrawler`. No operator would tune them, and a screen offering to would be a screen offering to break contact discovery.

Trap: `config()->set('eveil.crawl.delay_ms', 0)` in a test is a silent no-op. The suite still passes while every fetch sleeps for real, turning a 5s run into 19s. Test overrides go through `app(Settings::class)->set(...)`.

