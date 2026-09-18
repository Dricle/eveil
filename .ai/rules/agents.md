---
paths:
  - 'app/Ai/Agents/**'
---

# Agents

## A new agent class shifts hardcoded indexes in AppSettingsTest/AgentModelSettingsTest
`AgentSettings::known()`/`classes()` discovers agents by globbing `app/Ai/Agents/*.php` and sorts keys alphabetically. Adding a new agent class shifts every `agents.N.*` index used in `AppSettingsTest`'s Inertia assertions for every agent that sorts after the new one, and adds an entry to the hardcoded list in `AgentModelSettingsTest`'s "lists every agent it finds in the code" test. Update both when adding an agent, not just the new agent's own tests.

## Inject the full product portrait, never just what_it_does, when an agent needs to know the product
When an agent needs to know what this project sells (to judge fit, recognise a competitor, or write about the product), inject `EveilAgent::productPortrait()` - the full knowledge base in the same fields/order as the Knowledge Base settings screen (what it does, who it is for, value proposition, positioning, pricing model, key features, competitors, proof points) - never hand-pick just `knowledge_base['what_it_does']` plus competitors. A narrower slice reads as generic and starves the model of exactly the fields (positioning, proof points, who buys it) that make a judgment or a piece of copy specific rather than boilerplate.

Bug found and fixed in `CompanyQualifier::productContext()` and the new Reddit agents (`RedditOpportunityTriage`, `RedditReplyWriter`), which had all narrowed to `what_it_does` + competitors only. `productPortrait()` returns `'Not analyzed yet.'` when the knowledge base is empty - callers on a high-volume/cheap-model agent (like `CompanyQualifier`) should check for that string and omit the section entirely rather than print it, to keep the prompt lean when there is nothing to say yet.

Agents that legitimately skip this: pure page/data extractors (`ContactExtractor`, `ContactPageFinder`, `ListingExtractor`), agents deliberately blind to any one project for shareability (`ResultTriage`, instance-wide host verdicts), intent classifiers that act through tools and never write about the product (`ReplyHandler`), planners that already work off a target profile's derived criteria rather than the raw knowledge base (`DiscoveryPlanner`), and `RedditThreadTriage` (judges whether ANOTHER author's post is about THEIR product, not ours).
