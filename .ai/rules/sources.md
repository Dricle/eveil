---
paths:
  - 'app/Services/Discovery/Triage.php,app/Services/Discovery/Sources/**'
---

# Sources

## Triage classifies by candidate.website only, never sourceUrl
`Triage::sort()` decides index/entity/drop from `$candidate->website`. It used to read `sourceUrl ?? website` - looked harmless because `WebSearchSource`/`DegoogSearchSource` always set the two equal, but it silently breaks any source where they legitimately differ.

Concrete case that shipped broken and was only caught by reasoning through the full `RunProbe` pipeline (no test exercised it - `RedditSourceTest.php` called `RedditSource::search()` directly, bypassing `Triage` entirely): `RedditSource` sets `sourceUrl` to the reddit.com permalink for provenance/UI, regardless of whether `website` resolved to a real product URL. `reddit.com` is a LOCKED `other` host in `known_hosts` - classifying by `sourceUrl` meant Triage silently dropped every Reddit candidate, resolved-link ones included, before qualification ever saw them.

Rule: `sourceUrl` answers "where was this found" (provenance, shown in the UI, what `ReflectAndExpand`'s `productiveHosts()` groups by). `website` answers "what would we crawl" (the only thing Triage's index/entity question is actually about). Never conflate them again for classification. A candidate with `website === null` (a registry record, Reddit evidence with no confirmed link) skips classification entirely and is kept directly - `known_hosts` is never even asked about wherever it was found.
