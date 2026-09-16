---
paths:
  - 'app/Models/LinkedinPost.php,app/Http/Controllers/LinkedinPostController.php,app/Enums/LinkedinPostStatus.php'
---

# Enums

## LinkedinPost status is Draft|Published|Rejected, nothing else
No `approved` or `failed` status. Approve == publish, done synchronously in `LinkedinPostController::approve()` via `App\Actions\PublishLinkedinPost` (not a queued job — one HTTP call isn't worth a queue). A failed publish attempt leaves the row `Draft` with `last_error` set and visible, never destroys the draft state; the same Approve button retries it. Reject (`status = rejected`, optional `rejection_reason`) and delete (hard removal) are two different actions with different UI weight. `GenerateLinkedinPost::prompt()` feeds a project's own recent rejections (with reasons) back to the writer so it doesn't repeat them.
