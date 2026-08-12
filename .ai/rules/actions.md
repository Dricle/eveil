---
paths:
  - 'app/Actions/**'
---

# Actions

## One class per use case, and nothing else lives here
Settled 2026-08-12. `app/Actions/` holds the orchestrators — one class, one job the product actually performs: `AnalyzeWebsite`, `DeriveIcps`, `RunDiscovery`, `FindContacts`. They were split out of `app/Ai/` and `app/Discovery/`, which had become a mix of machinery and use cases.

The line to hold:
- **`app/Actions/`** — use cases. Fetch, call an agent, persist, return. Invoked by a command, a controller or a job. No HTTP parsing, no prompt text, no schema.
- **`app/Ai/`** — anything that IS AI: the agent classes and their prompts, `AgentSettings`, `ModelPricing`, the metering middleware.
- **`app/Discovery/`** — the machinery an action drives: `SiteCrawler`, `PageFetcher`, `HtmlText`, `JsonLd`, `ListingHarvester`, `EmailVerifier`, the sources.

`AnalyzeWebsite` sat in `app/Ai/` and contained no AI at all — it called an agent. That is the mistake to avoid: a class does not belong in `app/Ai/` because it mentions an agent, only because it IS one.

An action stays thin by construction. When one grows a private method that parses HTML, verifies an address or talks to an API, that method belongs in `app/Discovery/` (or a new domain folder) and the action calls it.
