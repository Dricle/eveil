---
paths:
  - 'app/Ai/Agents/LinkedinPostWriter.php,app/Services/Linkedin/**,app/Models/LinkedinAccount.php,app/Models/LinkedinPost.php,app/Http/Controllers/Linkedin*.php'
---

# Http Controllers

## LinkedIn posting: personal profile only, evidence-driven, always draft-and-approve
Scope is deliberately narrow: personal-profile posting via LinkedIn's official API (`w_member_social`, self-serve OAuth). No Company Page posting, no comment automation on other people's posts, no connection-request/DM automation — all three need either LinkedIn's gated Community Management API (unknown approval timeline) or a session-automation vendor, both explicitly rejected for this project (open-source, no vendor lock-in decided yet). Company Page content is a separate copy-paste-only feature (issue #32), no OAuth.

Content sources are evidence-driven, never generic: `LinkedinPostWriter` gets ALL available signals (knowledge base facts, a pending client-won company, recent news via `App\Services\Linkedin\NewsSearch` reusing the same SearXNG infra as discovery, today's date for optional seasonal framing) in ONE call and picks/justifies itself — no PHP-hardcoded source priority. A client-won result always produces a named AND an anonymized sibling post in the same call; approving one in `LinkedinPostController::approve()` auto-rejects the other via `LinkedinPost::sibling()`.

Every post is draft-and-approve, regardless of `projects.autonomy_level` — publishing to a public feed under the user's name is treated differently from a private 1:1 email. `App\Ai\Tools\DraftLinkedinPost` (Evie's tool) only ever creates a draft row, never publishes: mirrors the existing "no tool sends" rule for reply tools. Exactly one code path publishes: `PublishLinkedinPost` job, dispatched only from `LinkedinPostController::approve()`.

`LinkedinPost` is project-scoped (`BelongsToProject`) so routes take `int $linkedinPost` and look it up manually, never route-model-bound (same trap as `target-profiles.destroy`). `LinkedinAccount` is org-owned/project-granted like `EmailAccount`, not project-scoped.

Comment read/reply on your own post has no implemented method yet (`LinkedinClient`) — LinkedIn's exact API shape needs verifying against their live docs at build time, not assumed from training-data knowledge.
