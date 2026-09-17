---
paths:
  - 'app/Http/Controllers/LinkedinPostController.php,app/Http/Requests/LinkedinPostApproveRequest.php,app/Models/LinkedinPost.php'
---

# Requests Models

## Approving a LinkedIn draft requires an explicit account, chosen by the caller
`LinkedinPostController::approve()` no longer defaults to `Project::linkedinAccounts()->first()`. It requires `linkedin_account_id` in the request, validated by `LinkedinPostApproveRequest` against the pivot (`linkedin_account_project`) so a foreign/ungranted id fails validation, not a silent wrong-account publish. The frontend (`linkedin/Posts.vue`) only shows an account picker modal when the project has 2+ granted accounts; with exactly one, it submits that id straight through with no extra click. `LinkedinPostResource` now exposes `linkedin_account` (id + display_name) so the queue can show which account a post went out on once a project has more than one.
