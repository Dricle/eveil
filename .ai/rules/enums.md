---
paths:
  - 'app/Models/SocialPost.php,app/Http/Controllers/SocialPostController.php,app/Enums/SocialPostStatus.php'
---

# Enums

## SocialPost status is Draft|Published|Rejected, nothing else
No `approved` or `failed` status. Approve == publish, done synchronously in `SocialPostController::approve()` via `App\Actions\PublishSocialPost` (not a queued job — one HTTP call isn't worth a queue). A failed publish attempt leaves the row `Draft` with `last_error` set and visible, never destroys the draft state; the same Approve button retries it. Reject (`status = rejected`, optional `rejection_reason`) and delete (hard removal) are two different actions with different UI weight. `GenerateSocialPost` feeds a network's own recent rejections (with reasons) back to the writer so it doesn't repeat them.
