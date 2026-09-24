# How discovery finds companies

## What it is

Discovery is the step that turns a target profile into a list of real, qualified companies. It runs live against the web every time — nothing is pulled from a purchased or pre-built database. A run reads what it finds, scores each company against the target profile's own criteria, and keeps only the ones worth contacting.

## When it runs

A discovery run starts when you (or [Evie](/product/evie)) tell a target profile to go find companies — from the target profile screen, or by asking Evie directly ("find me 50 dental clinics in Lyon"). Each run has a budget: a cap on how many searches it makes, how many pages it reads, and how many companies it keeps, so a run always ends rather than searching forever.

## Where it looks

A plan comes first — Eveil reads the target profile and decides where to look before spending anything, and shows you that plan if your email [autonomy level](/product/autonomy) asks for approval. Two kinds of source feed it, picked by what the profile actually describes:

- **OpenStreetMap**, for anything with a physical address — shops, clinics, agencies, workshops. It's exhaustive and free: every business with a front door in the area searched, not just the ones that rank well on Google.
- **Web search**, for everything OpenStreetMap can't see — online-only businesses, professions, anything defined by what it sells rather than where it sits.
- **Reddit**, for a target profile whose buyers gather in a specific, real community — a launch-stage SaaS founder posting in r/SaaS, for example. Narrower than web search on purpose: only used when the profile genuinely points at a well-known subreddit, never guessed at. Reads posts and comment replies alike: a product mentioned three replies down a thread counts as much as one in the post itself, and a mention with no link in it gets a check of the author's own recent posts before it's kept anyway — with no confirmed site, but with the thread as evidence you can read and judge yourself.
- **Official business registries** (Belgium's KBO/BCE, France's SIRENE, the UK's Companies House and others), for a legal-entity search with no SEO bias at all — every registered company, not just the ones with a website. A registry record has a name, address and status, never an email or a site: the contact-finding step that follows spends one search of its own trying to find the company's actual website before giving up on it.

::: info Self-hosted only
Registries need a free API key you set up yourself — see [Configuration](/self-hosted/configuration). Without one, discovery just runs on the other two sources; nothing else is affected. On cloud this is already configured for you, nothing to do here.
:::

A target profile with no real geographic angle (most software, most online services) skips the map entirely and searches the web only; one built entirely around local premises does the reverse. Most profiles use two or three of these together.

## Two web search engines, not one

Web search runs against two independent, free search engines behind the scenes rather than one. Neither needs an account or an API key on your part. The reason is resilience, not more results: a single search engine occasionally rate-limits or blocks automated queries, and when that happens a query returning nothing looks identical to "this market genuinely doesn't exist" unless there's a second, independent source to check it against. Running both on every query means a rate-limited instance never gets mistaken for an empty market.

::: info Self-hosted only
SearXNG ships with the stack by default, and a second engine is an optional Docker Compose service you can turn on — see [Configuration](/self-hosted/configuration). On cloud, hosting is managed for you; there's nothing here to set up.
:::

## Directories count as leads too

A result pointing at a business directory (a "friteries in Namur" listing page, an industry association's member list) isn't discarded — Eveil reads it and treats every business it lists as its own candidate. For a business with no site of its own, a directory listing is often the only place its details are published at all. The directory itself can also be worth contacting: an agency selling to "launch platforms" wants Product Hunt as a lead, not just as a source of other leads.

## When a source proves unusually good

Sometimes a run stumbles onto a source that turns out to be a goldmine for the profile it's searching for — a launch directory, a community, a listing page — without having gone looking for it specifically. When several companies from the same source score well, Eveil notices mid-run and spends the rest of that run's budget going deeper there — other pages, other listings, other angles on the same source — rather than treating it as one more result among many and drifting on to something unrelated. This never affects what counts as a good match: it only decides where to keep looking once something is clearly working.

## When a search comes up empty

Nothing found on the first attempt gets one automatic retry with a different source before it's reported as a dead end — the same rate-limiting problem two web engines guard against, applied to the map source too. Past that, an empty result is treated as a genuine finding, not a failure: "your market is 40 companies, here they are" is the honest answer when that's what's out there, rather than quietly widening the criteria until the count looks better. If the target profile really is too narrow, Eveil says so and asks you before it starts contacting companies that don't fit.

## Qualifying and keeping

Every candidate is read and scored against the target profile before it's kept — industry, size, location, and a fit reason you can read back. A company found twice, by two different sources or two different runs, is only ever kept once. Nothing is contacted until the outreach step: discovery only builds the list.
