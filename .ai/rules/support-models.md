---
paths:
  - 'app/Http/Controllers/LinkedinStatsOAuthController.php,app/Support/LinkedinCredentials.php,app/Models/LinkedinAccount.php'
---

# Support Models

## LinkedIn performance polling needs a SECOND, separate Developer App
`r_member_social_feed` (Community Management API, for `GET /rest/socialMetadata/{urn}`) cannot be added as a scope on the same LinkedIn Developer App as Share on LinkedIn — LinkedIn refuses to let both products live on one app (confirmed by hands-on testing, not just docs). So polling is a second app, second client id/secret (`LinkedinCredentials::statsClientId()`/`statsClientSecret()`), second per-account OAuth connection (`LinkedinStatsOAuthController`, `linkedin_accounts.stats_access_token` etc.), gated end-to-end: instance must configure the second app AND the specific account must complete its own second connect step (`LinkedinAccount::hasStatsAccess()`) before `FetchLinkedinPostStats` will touch it. An account without it is skipped outright, never attempted.
