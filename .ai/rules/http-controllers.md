---
paths:
  - 'app/Ai/Agents/SocialPostWriter.php,app/Services/Linkedin/**,app/Services/Bluesky/**,app/Services/Social/**,app/Models/SocialAccount.php,app/Models/SocialPost.php,app/Http/Controllers/Linkedin*.php,app/Http/Controllers/Social*.php,app/Jobs/GenerateSocialPost.php'
---

# Social posting

## LinkedIn, X and Bluesky share one model, one writer, one driver interface
All three networks live on `social_posts` / `social_accounts` / `social_post_examples` with a `platform` column (`App\Enums\SocialPlatform`). One agent writes for all of them (`SocialPostWriter`, told which network: length and form differ, not voice), one job generates (`GenerateSocialPost`, one network per job), and each network has a driver behind `App\Services\Social\SocialClientInterface`, resolved by `SocialPlatform::client()`. Per-network settings are `{platform}_*` columns on `projects` (`_post_frequency`, `_next_post_at`, `_autonomy_level`, `_prompt_instructions`), read through the enum's column helpers, never by hardcoding a network. Adding a network is an enum case, a driver, and those columns. Each network is its own nav entry (`social.posts.index` with `{platform}`), deliberately, so a first-time user sees at a glance where Eveil posts.

What differs per network:
- **LinkedIn**: official API, personal profile only (`w_member_social`, OAuth). No Company Page posting, no comment automation, no connection-request/DM automation: those need LinkedIn's gated Community Management API or a session-automation vendor, both rejected. Stats need a SECOND developer app (see `.ai/rules/support-models.md`).
- **Bluesky**: its free API with an app password, not atproto OAuth (which needs a public HTTPS client-metadata URL a self-hosted laptop does not have).
- **X**: never through its API, which is paid per call. `XClient` is deliberately inert: the user copies the draft, posts it, and pastes the URL back (`SocialPostController::publish()`). X has no account row, no autonomy column (always supervised) and no stats.

Autonomy: `SocialPlatform::autonomyLevel()`. Autonomous publishes on its own, except a named client-win variant (only the anonymized one ever goes out alone, its sibling is rejected) and a project with several accounts on that network. A post Evie asks for (a `brief`) never auto-publishes: no Evie tool publishes.

`SocialPost` is project-scoped (`BelongsToProject`), so routes take `int $socialPost` and look it up, never route-model-bound. `SocialAccount` is org-owned/project-granted like `EmailAccount`.
