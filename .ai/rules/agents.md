---
paths:
  - 'app/Ai/Agents/**'
---

# Agents

## A new agent class shifts hardcoded indexes in AppSettingsTest/AgentModelSettingsTest
`AgentSettings::known()`/`classes()` discovers agents by globbing `app/Ai/Agents/*.php` and sorts keys alphabetically. Adding a new agent class shifts every `agents.N.*` index used in `AppSettingsTest`'s Inertia assertions for every agent that sorts after the new one, and adds an entry to the hardcoded list in `AgentModelSettingsTest`'s "lists every agent it finds in the code" test. Update both when adding an agent, not just the new agent's own tests.
