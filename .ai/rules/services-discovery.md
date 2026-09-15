---
paths:
  - 'app/Actions/FindContacts.php,app/Services/Discovery/WebsiteFinder.php'
---

# Services Discovery

## WebsiteFinder: enrichment for a no-website, no-email company (registry records, sparse listings)
`FindContacts::fromListing()` (the `domain === null` path) now tries two things in order, not one: a directly-published `facts['email']` (a directory listing), then - if that's absent but `facts['address']` exists (a registry record, or any listing/blog that named an address without a site) - `WebsiteFinder::find($name, $address, $project)`.

That service spends one web-search query (`"{$name}" {$address}`), then runs the SAME `HostRegistry::classify()` discovery already uses to sort a result into a directory vs. a company's own site. A "name + address" query routinely resurfaces the very directory or blog post the address came from, which names the company too - a bare `str_contains` check cannot tell that apart from the real site, since both mention the name. Only a host classified `HostKind::Entity` is even considered; the first Entity result is THEN checked with `str_contains` on the fetched page text as the identity check (is this page actually about this business, not just A business). Both checks have to pass. A wrong site means emailing a stranger about somebody else's fit score.

If a website is found and its domain doesn't already collide with another company in the project (`companies_project_id_domain_unique` is a partial unique index), the company is updated (`website`, `domain`) and `handle()` re-enters itself - now on the normal domain path (crawl, `ContactExtractor`, verify). A collision is left alone rather than merged: two rows already exist because they were found by different sources with no shared dedupe key, and merging isn't this step's job.
