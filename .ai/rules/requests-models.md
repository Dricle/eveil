---
paths:
  - 'app/Http/Controllers/SocialPostController.php,app/Http/Requests/SocialPostApproveRequest.php,app/Models/SocialPost.php'
---

# Requests Models

## Approving a draft requires an explicit account, chosen by the caller
`SocialPostController::approve()` never defaults to the project's first account. It requires `social_account_id`, validated by `SocialPostApproveRequest` against the pivot (`project_social_account`) so a foreign/ungranted id fails validation, and the controller also requires the account's platform to match the post's, so a LinkedIn draft can never go out through a Bluesky account. The frontend (`SocialPostCard.vue`) only shows an account picker when the project has 2+ active accounts on that network; with exactly one, it submits that id straight through.
