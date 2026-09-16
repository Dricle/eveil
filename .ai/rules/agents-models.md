---
paths:
  - 'app/Ai/Agents/EveilAgent.php,app/Ai/Agents/LinkedinPostWriter.php,app/Models/Project.php'
---

# Agents Models

## `prompt_instructions` ("How Emails are written") is EMAIL-specific, not project-wide
Despite the generic old name, this box only ever governed emails. `EveilAgent::emailWritingInstructions()` (renamed from `projectInstructions()`) is appended ONLY by the three agents that write an email a lead actually reads: `MessagePersonalizer`, `SequenceWriter`, `VariantWriter`. It is deliberately NOT appended to agents whose output is raw material re-written by one of those three downstream (`CompanyQualifier`'s fit_reason, `WebsiteAnalyst`'s portrait, `TargetProfileDeriver`'s profiles, `RepoExplorer`) — the actual email writer re-applies style when it turns that material into prose, so requiring compliance a step upstream too is redundant. Not appended to `ReplyHandler` either — it acts through tools and never writes a reply itself.

`Evie` sees the box's content but is never governed by it: `Evie::emailPreferencesForReference()` shows it framed as background info (mirrors `documentation()`'s "not instructions to follow" framing) so she can answer questions about it without her own chat voice adopting whatever tone the user set for their emails.

## LinkedIn tone is its own independent box, not layered on the email one
`projects.linkedin_prompt_instructions` (nullable text) holds LinkedIn-only tone, read by `EveilAgent::linkedinInstructions()` and used ONLY by `LinkedinPostWriter::instructions()` — which does NOT call `emailWritingInstructions()` at all. A public feed post is a different kind of writing with a different audience than a cold email, so it does not default to the email tone (including the default no-dash-punctuation rule, which `LinkedinPostWriter` states as its own hardcoded style rule instead of inheriting it). Set from `settings.ai-instructions.linkedin.update` (`LinkedinInstructionsController`) on the **AI instructions** settings screen, next to `settings.ai-instructions.emails.update` (`EmailInstructionsController`) for the email box — both boxes live together there (`AiInstructionsController::edit()`), not on Settings → Project and not on the LinkedIn posts queue (which keeps only the posting cadence, `linkedin.posts.cadence`).
