---
paths:
  - 'app/Services/Discovery/**'
---

# Discovery

## Lead discovery: the differentiator, and its stack
AI lead discovery is the product's edge — not database size (Apollo has 275M contacts, we never will). Our edge: target profiles derived automatically from the project knowledge base, fresh long-tail results (local businesses, new companies, niche directories) and shared context between discovery and personalisation.

Pipeline, five stages, each independently testable: target profile derivation → company discovery → qualification/fit scoring → contact + email discovery → verification.

Stack:
- Search: SearXNG as an extra docker compose service. Free, no API key, identical code in both editions. Watch for rate-limits/blocking; a paid driver (Brave/Serper) can be added behind the same interface if it proves unreliable.
- Also free and key-less: OpenStreetMap Overpass API for local-business discovery, GitHub API for developer target profiles.
- Email verification: in-house MX check + SMTP `RCPT TO` probe, no send. Must detect catch-all domains and flag those addresses `risky` rather than `valid`. Gmail/Outlook block probes — treat as unknown, never as invalid.
- Email addresses from pattern inference (one known address on a domain → derive `first.last@`) are stored with `email_source=inferred` and always verified before any send.

Fetching starts with plain HTTP only. Add a headless browser container only once the JS-render failure rate is measured, not before. Respect robots.txt and rate-limit per domain. Dedupe companies by domain and contacts by email from the first line of code — a re-run must never duplicate.

## Per-project isolation, shared raw page cache
ADR-014. Companies and leads stay scoped to their project — no shared company registry. Re-fetching is avoided by an instance-level raw page cache: key = normalised URL, with a TTL, public content only.

Why no shared registry: the expensive part of discovery is LLM qualification, not the HTTP fetch, and fit score plus its rationale are target-profile-specific — the same company scores 90 for one product and 20 for another. Sharing would only save the target-profile-independent part (page content, firmographics) at the cost of an extra entity, a join everywhere, and an arbitration rule for when two projects disagree on a company's facts.

Cache rules: never store authenticated or logged-in content, key on the URL, honour the TTL. It is public web data, so it is safe to share across tenants in cloud; if that ever becomes a concern, scope the cache per organization — nothing else changes. The cache pays off on re-runs of a single project as much as across projects.

## target profiles: as many as the agent derives, free CRUD
ADR-015. An target profile (Ideal Customer Profile) is the structured portrait of the target customer — sectors, size, geography, job titles, technologies, trigger signals — derived from the project knowledge base. It drives where the agent searches and how each company is scored.

The agent derives as many as it judges necessary (no imposed count) and the user can create, edit and delete them freely. A product usually serves several markets; flattening them into one average profile targets nobody.

Schema consequences, non-negotiable:
- Fit score does NOT live on the company. The same company scores 90 for one target profile and 20 for another. Split `companies` (firmographic facts, deduped by domain within the project) from `company_target_evaluations` (company_id + target_profile_id → fit_score, fit_reason). Otherwise two target profiles finding the same company overwrite each other's evaluation.
- A lead surfaced by two target profiles is not contacted twice: a lead belongs to at most one active campaign per project. The second target profile records the overlap without re-engaging.
- Each active target profile is one more discovery run, so one more budget. No hard cap, but the UI must show expected cost when several are active.

The main screen stays a straight line — the CRUD is available, never required to move forward.

## Insufficient discovery: diagnose before widening
ADR-020. "Found nothing" is four distinct failures and they need different responses:
- target profile too narrow (3 companies instead of 100) → widen one criterion.
- Wrong source (0 results but the market exists) → switch tool, not criteria.
- Fit uniformly low (300 found, none above 40) → **the target profile is wrong; NEVER widen** — escalate to the user. Widening here is the worst case: 100 off-target leads get contacted and the user's domain takes the complaints.
- No emails (companies qualified, contacts unreachable) → extraction problem, not targeting.

Market exhaustion is a RESULT, not a failure. "Your market is 40 companies, here they are" beats scraping noise to hit a quota — no competitor says this, they sell on volume.

Widening is indexed on `projects.autonomy_level` (ADR-009): supervised proposes and waits; semi_auto and autonomous widen alone and report what they relaxed. Shared bounds for all three: one axis at a time, two steps maximum, in this order — geography → size → adjacent sectors → job titles. Never two axes at once or you cannot tell what worked. Log and display every relaxation.

Widening attempts count against the ORIGINAL run's budget and never open a new one — otherwise the loop burns credits producing nothing.

## The crawler
`SiteCrawler` fetches the homepage, then follows a handful of links scored by path (`about`, `pricing`, `features`… ahead of `blog`, `news`, `tag`), same host only, bounded by `config('eveil.crawl.max_pages')`. A homepage alone rarely says what a product costs or who it is for.

- Plain HTTP only. No headless browser until the JS-render failure rate is measured.
- `RobotsPolicy` and `PageFetcher` are SINGLETONS: they hold the parsed robots.txt per host and the last-fetch timestamp the politeness delay is measured from. Resolving them per crawl re-fetches robots.txt and drops the throttle.
- Pages land in `crawled_pages`, the one instance-wide shared table (ADR-014). Public content only, keyed on `Url::normalize()`'d URL — the same normalisation the dedupe uses, so it lives in `App\Support\Url` and nowhere else.
- A missing `Content-Type` is accepted; only an explicitly non-HTML one is rejected. Small sites omit the header and refusing them would silently skip readable pages.
- Pages are passed around as `App\Support\ParsedPage`, not array shapes: array shapes are not covariant inside a `Collection` and fight PHPStan for no benefit.
- `Http::response('<html>…')` in tests sets NO Content-Type header — a fake that looks right can still be rejected by content sniffing. Remember it when writing crawler tests.

## Four traps live discovery runs hit, all covered by tests
Live runs return real local businesses with usable fit reasons, so the no-purchased-database thesis holds. Four things nearly stop them:

- **Overpass answers HTTP 406 to Guzzle's default User-Agent.** It asks that clients identify themselves. Without the `EveilBot` header the source returns nothing, forever.
- **Place names repeat across the world.** A probe on "Charleroi" without a country also returns a Subway in Pennsylvania. Every Overpass probe carries an ISO 3166-1 country, resolved first, with the town looked up inside it via `map_to_area`.
- **A dead source and an empty market look identical.** A run reporting "no candidate at all, the sources were wrong" is indistinguishable from a 406 on every probe. Sources record their failures (`ReportsFailures`), the run stores them in `stats.source_failures`, and the command prints them. Never let a source fail silently.
- **PostgreSQL text columns reject NUL bytes and invalid UTF-8**, and real pages contain both — one mis-encoded site kills a whole run two thirds of the way through. `PageFetcher::storable()` strips them, and qualification is wrapped per candidate so one bad site can never cost the run everything already found.

Cost measured: $0.0025 per qualification on Haiku, below the $0.0035 estimate. Extraction on the cheap model comes in under estimate; generation on the planner comes in over. Size the OUTPUT when estimating a new action.

## Contact extraction on local micro-businesses
Measured on a batch of qualified local food businesses: **a phone number on every one and not a single published email address** — one site named a person with no address. Guessing `info@` recovered two of four; both came back `risky` because the domains are catch-all, so acceptance proved nothing.

Read that as a finding about the segment, not a bug: **local micro-businesses publish a phone and a Facebook page, not an email.** Roughly half are email-reachable at all, and mostly through a guessed generic address. Any target profile of this shape should be expected to convert poorly into email leads — a dark kitchen, an agency or a SaaS will do far better. Say so to the user rather than reporting an empty run as a failure; `--guess-generic` is opt-in for exactly that reason.

Mechanics worth remembering:
- Port 25 is NOT blocked from the Sail container — the SMTP probe really ran and really detected catch-all domains. Do not assume the probe is decorative in dev.
- Only `invalid` blocks a send. Catch-all is `risky`, a refused probe is `unknown`, and both stay sendable (ADR-007).
- Guessed addresses are stored `inferred` and are only kept when the mail server accepts them — never guessed-and-stored blind.
- The phone is kept on `companies.facts` even when no email exists: for this segment it is often the only way in, and a later channel will want it.

## Partner profiles: same machine, different target (ADR-031)
An target profile carries a `type`: `customer` or `partner`. A partner profile describes not who buys, but **who already touches the buyer** — who visits them, who invoices them monthly, who is legally imposed on them.

The reason it earns its place is measured, not theoretical: four qualified local food businesses produce two leads, both guessed `info@` and both `risky`, because that segment publishes a phone and not an email. Their intermediaries — a wholesaler, a brewery, a sector-specialised accountant, a food-focused web agency — are B2B companies with real sites, named people and published addresses. Near-total reachability, and one accountant is fifty restaurants.

Discovery, qualification and contact extraction are unchanged. Two things differ:
- A partner profile carries `access_angle` (how it touches the customer, and how often) and `partnership_angle` (why the deal is good for them — which becomes the email opener).
- **The outreach sequence differs in kind**: the mail to a wholesaler is not "buy this", it is "your reps visit 3,000 restaurants, here is the revenue share". Different value proposition, different definition of a positive reply.

Prioritise the **legally obligatory** intermediaries — fiscal cash register, HACCP, food-safety, business counters. Captive clientele, few of them, enumerable.

Never cite a company the pipeline has not found, fetched and qualified. An LLM produces plausible false names readily, and the whole point of this product over a strategy document is that its names are verified.

## Directories are a source, and search results have two natures (ADR-033)
Search engines rank companies that do SEO. A large part of the target market has no site, only a Facebook page, or a site nobody reaches before page 20 — and those are frequently the best targets, because nobody else is calling them.

Never drop a result because it points at a directory. `pagesdor.be/friteries/namur` is not a company, it is **two hundred companies**, and it is the only place a site-less business publishes an email. **The host verdict is a router, not a filter**: a result is either an *entity* (→ candidate) or an *index* (→ harvest).

**The model navigates, PHP extracts.** The LLM decides WHERE to look; code does the volume. Harvesting a listing page is plain PHP — `sitemap.xml`, then JSON-LD `LocalBusiness`/`Organization`, then stored CSS selectors, then the LLM extractor as a last resort. Directories nearly all emit JSON-LD because SEO is their business, so one parser covers most of them. The model never sees the two hundred entries, only `"60 saved, 41 with a site, 12 with an email"`.

`directories` is **self-populating**: host, yield, sectors, countries, extraction mode, recorded from what actually produced. Hand-curating it is chicken-and-egg — you cannot know in advance that `pagesdor.be` matters for friteries. Adding a directory must stay a row, never a PHP class; that is a deliberate open-source contribution lever.

Not to be built: a Facebook scraper. Blocked, against ToS, fragile. Facebook-only businesses are reached through OSM `contact:facebook` and through directories.

Noted but not scheduled: open company registries (KBO/BCE, SIRENE, Companies House) are exhaustive by construction and free — no SEO bias possible, NACE code plus commune beats any query. They carry no email, so they feed enrichment, not sending. They fit the existing `DiscoverySourceInterface` interface. Do them after directory harvesting, not before.

## Discovery is a job graph, not an agent tool loop (ADR-033)
Each node is a queued job with minimal context, its own `discovery_tasks` row, its own cost: `PlanDiscovery` (AI) → `RunSearchQuery` (no AI) → `TriageResults` (AI, batched ~20 URLs) → `HarvestListing` (no AI) → `ExtractEntities` (AI only when JSON-LD failed), alongside `RunOverpassProbe` (no AI), with `ReflectAndExpand` (AI, reads aggregates) enqueuing the next wave. Most nodes never touch an LLM.

A tool loop was considered seriously and three of the four objections against it collapsed — `laravel/ai` already accumulates usage across steps in `TextGenerationLoop::buildFinalResponse()`, so metering needs no code; `Contracts/Approvable` pauses and resumes per tool; and the supervised notch (ADR-009) covers strategy and email content, not each fetch. **The reason that survived: a tool loop's cost grows quadratically with depth**, because every step resends the whole history. A 40-step scout is not 4× a 10-step one. The job graph keeps context flat.

Three concrete benefits, not theory: a job re-runs from the UI (the row IS the button), a job checks `crawled_pages` on pickup and deletes itself when its work is moot, and a crash at step 35 does not lose the first 34.

What it costs: reasoning continuity. Fan-out jobs are amnesiac where a conversation remembers "pagesdor yielded 60 in Namur, try Charleroi". That memory must be DESIGNED in the database instead of coming free from the context window — that is `ReflectAndExpand`'s job, and it reads counters (yield per directory, barren queries, uncovered communes), never pages. That is what keeps it cheap. It is also where the ADR-020 diagnosis runs; `wrong_source` finally has a concrete answer — switch directory.

Budget and cancellation are ONE flag: `discovery_runs.status`. Out of credits → `exhausted`; user cancels → `cancelled`. Queued jobs read it on pickup and delete themselves. No job registry, no killing workers. `discovery_tasks` is a dedicated table and not Laravel's `jobs`, which loses the row on success and so cannot back a history or a re-run button.

A tool loop is still the right tool INSIDE one directory whose pagination resists. Local and bounded, not the overall architecture.

## HtmlText emits markdown, and lives in Support
`HtmlText` emits markdown rather than `text` and `links` separately. Separate lists throw away the pairing: a directory page gives 200 names on one side, 200 URLs on the other, and nothing joins them. Markdown keeps `[Acme Plumbing](/company/acme-plumbing-4412)` intact — the difference between a usable listing page and an unusable one. `mailto:` and `tel:` are kept verbatim in the markdown while `Url::resolve` drops them, because they are contact details rather than links to crawl; an address that only appears in an href would otherwise be invisible to the extractor.

`nav`, `header` and `footer` are NOT stripped. They look like chrome until you notice pagination lives in `nav`, and dropping it stops a listing harvest at page one.

`HtmlText`, `ParsedPage` and `Url` live in `app/Support/`, not here — parsing HTML and resolving a URL are not discovery concerns. `ParsedPage` belongs with `HtmlText`: Support must not depend on a service.

Not replaced by a package, and the option was checked: `league/html-to-markdown` is the mature choice but resolves no relative URLs against a base, extracts no title, lang or link list, and would leave us owning most of this class anyway. Revisit only if the hand-rolled renderer starts failing on real pages.

## Learned host verdicts need a human override and an expiry
A verdict the model got wrong caches with exactly the same confidence as one it got right, instance-wide and forever: misclassify a real prospect's site as `noise` and it is invisible to every project, in every organization, permanently. Two guards, both cheap now and awful to retrofit:

- **`is_locked`** — a superadmin can see and edit the table in the UI, and a row a human touched is never overwritten by a model. The screen is the escape hatch for exactly the failure above.
- **`last_verified_at`** plus a re-check window — `blocked` must not be permanent, because sites change CDN configuration, and a directory can die. An expired row is re-judged on next encounter.

Everything else about the registry is designed to avoid asking twice; these two exist so that "never ask twice" cannot become "wrong forever".

## A host verdict is STRUCTURAL, never a judgement of relevance
`known_hosts.kind` answers *"what is this host?"* — one organisation, or a list of them — and never *"would a customer care?"*.

Filing job boards, marketplaces, delivery platforms or code hosting under `noise` is the standing mistake here, and it is wrong for whole categories of buyer:
- a recruitment agency prospects companies that are **hiring**, so Indeed is an index, not noise
- a food-tech product prospects restaurants, so Deliveroo is an index
- a developer-tool profile lives on code hosting, and the GitHub API is already a declared source — a floor that filed it as noise would contradict that
- a newspaper is an `entity`: one organisation's site, and a prospect for anyone selling to publishers

**The registry is shareable ONLY because the verdict is profile-blind.** Encode relevance in `kind` and the answer stops being reusable across projects, which destroys both the instance-wide table and the cloud cold-start argument that rests on it. `ResultTriage` is therefore never told what the target profile is, and its prompt says so explicitly.

Relevance is `CompanyTargetEvaluation`'s job, per profile. A restaurant profile that harvests Indeed gets companies scored near zero — mildly wasteful, never wrong. That asymmetry is deliberate and matches the prompt's tie-breaker: harvesting a single company costs one page, discarding a real directory loses every business on it.

What is structurally neither a company nor a list of companies for anybody — search engines, encyclopaedias, forums, and the social platforms — lives as LOCKED rows in `known_hosts`, seeded by `KnownHostSeeder`. The social ones are there for a different reason than the rest: structurally they do list organisations, but automated access is blocked and their terms forbid it, so the kind is moot.

**The fourth case is called `other`, not `noise`.** It states what a host is not; it never claims the host is worthless. A forum thread naming the best plumbers in a city, or an article listing five companies that just raised, are real leads on a host that is not itself a directory. We drop them today only because we classify HOSTS and harvest HOSTS — a page-level pass over `other` results that ranked for a targeted query is the obvious later move, and calling the case `noise` would quietly argue against ever building it.

## `index` and `entity` are not alternatives — a directory is also a company
Routing an `index` host to the harvester and nowhere else — scraped for its listings, never considered as a lead itself — silently makes a whole category of buyer unserviceable. A founder whose target profile is "launch platforms and startup directories" wants Product Hunt and BetaList AS LEADS. Someone selling moderation tooling wants review sites. Someone selling analytics wants marketplaces. Every directory is run by a company.

So an `index` host produces **both**: the harvest of its listings, and a candidate for the host itself, built from the host root rather than the listing URL that matched. Qualification decides what it is worth — for a restaurant profile the directory scores near zero and costs one page fetch plus $0.0025, which is the right way round. Leaving it out costs an entire market.

Corollary for the prompt: nearly every index is also an entity, so `ResultTriage` is told to answer `index` whenever a host publishes lists, and to reserve `entity` for hosts that publish none. Answering `index` forecloses nothing downstream.

The pattern to watch for: any time the registry's verdict decides what we DO rather than what a host IS, relevance has leaked back in. The verdict is structural; behaviour is chosen per run, and worth is decided per profile at qualification.

## Headless rendering: deferred on purpose, with the trigger written down
Not built. Plain HTTP only remains the rule.

**Measured cases so far: zero.** `resto.be` is not JS-only — the server sends 737 KB and the extractor reads 23 businesses out of it. `pagesdor.be` is `blocked`, not unrendered. Nothing so far would have been saved by a browser.

`harvest_status` is precise enough to make the decision on evidence rather than instinct:

| status | meaning | would a browser help? |
| --- | --- | --- |
| `blocked` | nothing fetched | **no** |
| `js_only` | fetched, under 500 chars of text — a shell | **yes** |
| `no_listing` | fetched, real text, nothing on it | no |
| `jsonld` / `llm` | worked | n/a |

**Trigger: revisit at 10 or more hosts sitting on `js_only`.** `KnownHost::where('harvest_status', 'js_only')->count()` is the whole decision. Below that the extractor is reading what servers actually send, and a gigabyte of Chromium buys nothing.

**Rendering does not fix `blocked`, and expecting it to is the expensive mistake here.** Imperva and Cloudflare fingerprint a headless browser too, so a vanilla Playwright is detected almost immediately. Beating that needs stealth plugins and residential proxies — an arms race, a running cost, and aimed at a site that put bot protection in front of its data and `Disallow` in its robots.txt. We respect robots.txt; do not chase this, and say so plainly rather than pretending the capability exists.

Shape when it is built, so the decision is not re-litigated:
- A renderer **chain** in config, escalating on OUTCOME rather than per-host setting: fetch plain, and only retry through a renderer when the extraction came back `js_only`. `HarvestStatus::needsRendering()` already marks the hook.
- Write the result back to `known_hosts`, so a host known to need rendering goes straight there next time and never pays the wasted plain fetch again. Same compounding as every other verdict.
- **The sidecar is the primary, a hosted API is the alternative** — never the reverse. ADR-006 says discovery works self-hosted without subscribing to anything, so a third-party renderer can never be the only path. `browserless/chromium` as an OPTIONAL compose profile, off by default: ~1 GB image, 200-500 MB per page context, 2-5 s per page against ~200 ms. On a small VPS that is the whole machine, so a self-hoster opts in.
- Cloudflare Browser Rendering, ScrapingBee or Zyte fit the same seam as a driver for operators who would rather pay than run Chromium. Optional, keyed, never assumed.
- Do not build the interface before the second implementation exists.

## Path keywords are learned, and there is no list anywhere
There is no list of contact-page path keywords anywhere — not a const, not a seeder. Any such list covers a few languages and silently misses `/contacto`, `/chi-siamo`, `/om-oss`, `/kontakty` and every market nobody thought of; moving it into a seeder is the same list in a different file.

**`path_hints` starts EMPTY.** The first site asks `ContactPageFinder`, `learn()` writes the answer back, and within a handful of sites the common words are all there. A cold start costs a few tenths of a cent, once, for the whole instance — instance-wide for the same reason as `known_hosts`: which word a site puts in the URL of its contact page is a fact about the web, not about a customer.

Full lifecycle, no curation required:
- **create** — the model picks links; `learn()` stores the last meaningful segment, so `/nl/over-ons` teaches `over-ons` and not a path that only ever matches one site. Under four characters, numeric, or a file extension teaches nothing and is skipped.
- **rank** — `matched`/`hits`, so what keeps working is tried first.
- **retire** — `review()` deletes a fragment whose pages keep not delivering.

**The ratio is also the guard against an over-generic fragment**, which is the real hazard of learning: a model that answers `/informations` once writes a token that then selects a page on half the sites on the instance, forever. There is deliberately NO stop-list of banned words — that would be another hardcoded list. A fragment that is too generic simply fails to deliver and the ratio catches it. Judged only after 8 attempts, because a good fragment can start badly, and never on a locked row.

Resolution walks the label chain: `fr.wikipedia.org` answers from the `wikipedia.org` row and `nl.pagesdor.be` from `pagesdor.be`, stopping at two labels. Neither a substring match nor a per-hostname lookup: the latter judges the same directory once per language subdomain. Wrong for `co.uk`-style suffixes, but only if somebody creates a row for one, and the alternative is a public-suffix dependency for a case that has not come up.

`ContactPageFinder` reads the markdown link list, which is why `HtmlText` emits markdown: `[Chi siamo](/chi-siamo)` carries the LABEL as well as the path, and the label is what makes an unfamiliar path readable.

The bootstrap problem that rules out learning from success alone: you only fetch what already matches, so you can never learn a word you have never seen. The model call breaks that loop and only fires on a miss.

`PathHintKind::Product` exists but nothing records against it — `SiteCrawler::PRIORITY_PATHS` is still a const, because "did this page improve the knowledge base?" has no crisp per-page signal the way "did this page contain an email?" does. Wire it when there is something honest to count.

### Lists still hardcoded, and what to do with each
- `SiteCrawler::PRIORITY_PATHS` — should become `PathHintKind::Product`, blocked on the reward signal above.
- `KnownHostSeeder` — defensible as-is: unlike path hints, its cold start is the cloud-versus-self-hosted argument, and a wrong host verdict is expensive rather than a fraction of a cent.
- `JsonLd::NOT_A_BUSINESS`, `FindContacts::COMMON_LOCAL_PARTS` — leave. Schema.org type names and `info@`/`contact@` are not language- or market-dependent in the way a URL path is.

## Closed-world lists: the standing test, and where each one landed
The test: code that enumerates a closed set of facts about an open world will be wrong, because you cannot predict every domain, language or market. Apply it to any new list, and remember the worst cases are not in discovery but in **verification, where being wrong means a bad send**.

**Fine as they are** — these enumerate sets a spec or we define, not the world: `HtmlText::SKIP`/`BLOCK`/`HEADINGS` (HTML tag names), `Url`'s http/https, `AgentSettings::DEFAULT`, the enums.

**Open-world, and handled accordingly:**
- **Email patterns are a grammar, not a list of shapes.** Matching `first.last`, `flast` and a handful of others one by one silently fails on `first-last`, `last-first`, `f_last`, `firstl` — and a missed shape is not a quiet miss: `detect()` returns null, the site's real convention is lost, and the fallback guesses one that BOUNCES. `EmailPattern` generates shapes from the name's pieces (`first`, `last`, `f`, `l`) crossed with separators (`.`, `_`, `-`, none), so a convention nobody wrote down is recognised the first time it appears. Full names beat initials when both fit, and two bare initials (`md@`) are refused — they identify nobody, so inferring anyone else's address from them produces bounces.
- **Disposable domains are a maintained dataset, never a const.** There are 8 201 of them and new ones weekly; a throwaway domain has working MX and passes every other check, so each miss is an address marked valid and sent to. NOT learnable — you would have to send to a throwaway to find out — so it is treated as what it is, a public dataset: bundled at `database/data/disposable-email-domains.txt` so a fresh install needs no network, refreshed by `eveil:refresh-disposable`, stored on the `toxic` suppression layer, which already means "instance-wide, fed only by public lists and our own detection". `replaceWith()` is transactional and the command refuses a response under 1 000 domains, because a half-applied refresh silently starts accepting what it used to reject.

**Probe refusers live in `mail_hosts`, learned.** A hardcoded provider list misses Proton, Zoho, Fastmail, GMX, OVH, Infomaniak and every corporate Exchange. Rank this one correctly: a miss is NOT a wrong answer. Without the shortcut we probe, get nothing, and return `unknown` — the same verdict, five seconds later. It is a speed guard, not a correctness guard.

Learned free, because the refusal is the signal. Keyed on the MX HOST with parent-domain fallback, which is where the leverage is: one `google.com` row covers every customer domain Google hosts. Marked refusing after 3 conversations that all ended without a verdict — one silence is greylisting, two is bad luck.

**The hazard that shaped the design: port 25 is blocked on most hosting.** If a failed connection counted as a refusal, the first run on such a box would mark every mail provider on earth as one, and then never probe again, anywhere. So `ProbeOutcome` separates `Unreachable` (never got a conversation — says nothing about the server, discard) from `NoVerdict` (talked, and it would not say — that is about the server). Only the latter is recorded. The seeded certainties are locked, so observation never moves them.

**Still open, in order:**
- `FindContacts::COMMON_LOCAL_PARTS`, `SiteCrawler::PRIORITY_PATHS`, the crawler's dead-end paths — all language-bound, all the `PathHints` case, already-built mechanism waiting to be wired.
- `JsonLd::NOT_A_BUSINESS` — leave. It is a denylist with a fallback (a node still needs a name plus a contact detail), so a miss lets noise through that qualification filters, rather than dropping a real company.

