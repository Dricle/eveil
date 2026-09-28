---
paths:
  - 'app/Http/Controllers/LinkedinStatsOAuthController.php,app/Support/LinkedinCredentials.php,app/Models/SocialAccount.php'
---

# Support Models

## LinkedIn performance polling needs a SECOND, separate Developer App
`r_member_social_feed` (Community Management API, for `GET /rest/socialMetadata/{urn}`) cannot be added as a scope on the same LinkedIn Developer App as Share on LinkedIn — LinkedIn refuses to let both products live on one app (confirmed by hands-on testing, not just docs). So polling is a second app, second client id/secret (`LinkedinCredentials::statsClientId()`/`statsClientSecret()`), second per-account OAuth connection (`LinkedinStatsOAuthController`, the `stats_*` columns on `social_accounts`), gated end-to-end: instance must configure the second app AND the specific account must complete its own second connect step (`SocialAccount::hasStatsAccess()`) before `LinkedinClient::likeCounts()` will touch its posts. A post whose account lacks it is skipped outright, never attempted.
