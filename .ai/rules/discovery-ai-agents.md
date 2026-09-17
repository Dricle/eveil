---
paths:
  - 'app/Actions/FindSubreddits.php,app/Services/Discovery/SubredditFinder.php,app/Ai/Agents/DiscoveryPlanner.php'
---

# Discovery Ai Agents

## Reddit subreddits are resolved once per profile, never guessed per run
`DiscoveryPlanner` used to name a subreddit from its own training knowledge each run ("only if you're confident it exists"). No verification existed. Fixed: `TargetProfileDeriver` proposes `subreddit_topics` (keywords, never subreddit names - it can't be trusted to name a real one) as one more field in its existing single call. `SubredditFinder` (mechanical, no-AI, mirrors `WebsiteFinder`) resolves topics into real, currently-existing subreddits via Arctic Shift's `/api/subreddits/search?subreddit_prefix=` (verified live; Reddit's own subreddit-search is a dead end - `reddit.com` is locked `other`, the endpoint now returns a login shell not JSON). `FindSubreddits` is the one entry point, called by `DeriveTargetProfiles::store()` for every new agent-derived profile and re-runnable via `eveil:find-subreddits {id}` to backfill a human-authored one.

Stored on `TargetProfile.criteria.subreddits`, deliberately NOT on the project knowledge base: relevance is per-profile (one project's several target profiles serve different Reddit communities), same reason `criteria.sectors`/`search_queries` already live there.

`DiscoveryPlanner`'s prompt now says: only probe a subreddit from the profile's own `subreddits` list; an empty list is a real answer (skip Reddit this run, never invent one); the old "name one you're confident exists" permission survives ONLY when the `subreddits` key is absent entirely (an old profile never backfilled) - do not remove that fallback casually, it is what keeps a pre-existing profile working until someone explicitly backfills it.

Buyer, not competitor: topics must describe the BUYER'S communities, never the product's own category (a company selling outbound automation does not want other outbound-automation vendors' subreddits) - same instinct as `CompanyQualifier`'s competitor exclusion.

`SubredditFinder` filters `quarantine` (Reddit's own flag for genuinely extreme or harmful content) but deliberately NOT `over18`: Eveil has no stated vertical exclusion, and the adult industry is a legitimate market like any other. Confirmed with the user directly after a first pass filtered NSFW by default with no real basis - do not reintroduce that filter without a real product reason.

## SubredditFinder also verifies model-guessed names now; two dead ends found doing it (2026-09-17)
`criteria.subreddits` (written by `FindSubreddits` right after every agent-derived profile is created) was reaching the frontend fine via `TargetProfileResource`, but `resources/js` never rendered it anywhere - a pure UI gap. Now shown read-only in `targets/Profile.vue`'s "How the run finds them" card.

The real fix needed was for `SubredditFinder`'s prefix-only match silently returning zero results for any multi-word topic ("b2b sales" matches no real subreddit name). Two approaches were tried and DISPROVEN LIVE before landing on the one that ships - both are worth not re-attempting:

- **A full-text "which subreddits discuss this topic" pass, via `/api/posts/search?query=<topic>`.** Looks right from the docs (`query` "searches title and selftext"), but the live API 400s: `"'query' query parameter requires one of: author, subreddit"`. Arctic Shift has NO way to search Reddit content for a keyword without already knowing which subreddit or author to look in. Confirmed against the real endpoint, not just the README - always test a documented-but-unused API path live before trusting it.
- **Splitting a multi-word topic into its individual words and prefix-matching each** ("agency life" -> "agency", "life") technically works (no 400) but measurably makes results WORSE: "life" alone resolves to r/LifeProTips (22.7M subscribers), r/lifehacks (14.4M), r/lifeisstrange (a video game community) - all outrank the genuinely relevant hits by subscriber count and bury them in the final list. Subscriber count and quarantine say nothing about topical relevance, so a generic word's unrelated mega-community routinely crowds out the real, smaller, on-topic ones. Do not reintroduce without a real relevance signal, which Arctic Shift's subreddit data does not carry.

What ships, three independent ways to find a NAME, one way to trust it (all in `SubredditFinder::find(array $topics, array $guesses = [])`):

1. **Topic name-prefix match**, as before (`subreddit_prefix=`).
2. **Model guesses.** `TargetProfileDeriver` also proposes `subreddit_guesses` - actual candidate subreddit names the model recognises from its own training, not just keywords - alongside the existing `subreddit_topics`.
3. **A real web search.** The topics are folded into one SearXNG query ("best subreddits about `<topics>`" - same instance `WebSearchSource` already queries), and the `title`/`content`/`url` of each result is regex-scanned for `r/word` mentions - no page fetch needed, curated listicles put the names right in the snippet. This is the strongest signal of the three: live-tested, it recovered r/SideProject and r/indiehackers (both real, both missed by prefix matching, since neither `"side project"` nor `"indie hackers"` prefixes a subreddit name) from actual "27 Best Subreddits for SaaS Founders"-style articles.

Every name from (2) and (3) is verified with the same exact-name lookup (`subreddit=name`) before being trusted - subscriber floor, not quarantined - same "model navigates, code verifies" split as `WebsiteFinder` and directory harvesting. A wrong guess or a false regex hit is silently dropped, never surfaced as an error. Names already resolved by the prefix pass are never re-verified.

Trap hit shipping this: **a new outbound host needs faking in every test that reaches `SubredditFinder::find()`, not just the one you're adding a test for.** Adding the SearXNG call made `DeriveTargetsCommandTest`'s `beforeEach` (which only faked `arctic-shift.photon-reddit.com`) leak real network requests silently - `Http::fake()` with specific patterns and no catch-all lets an unmatched host through for REAL rather than erroring, so tests still passed, just slowly (17s instead of 2s) and non-hermetically. Caught by noticing the timing, not a failure. Any test exercising `find()`/`FindSubreddits::handle()` needs both hosts faked; a test using `Http::swap()` to override the shared default must re-add BOTH.

Trap hit while building the (reverted) mentions pass: `Collection::filter(is_string(...))` throws `ArgumentCountError` - Collection's `filter`/`Arr::where` always calls the callback with `($value, $key)`, and a first-class callable of a strict-arity builtin like `is_string` rejects the extra arg. Use `fn ($v) => is_string($v)` instead whenever filtering a Collection (not a plain array - `array_filter` only passes one arg and is fine).
