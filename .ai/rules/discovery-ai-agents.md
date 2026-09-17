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
