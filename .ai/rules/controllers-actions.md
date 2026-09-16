---
paths:
  - 'app/Models/LinkedinPost.php,app/Models/LinkedinPostExample.php,app/Http/Controllers/LinkedinPostController.php,app/Actions/FetchLinkedinPostStats.php'
---

# Controllers Actions

## LinkedIn's two proven-post pools trust different signals — never mix them
A user clicking "mark as successful" (`LinkedinPostController::promote()`) ONLY ever stamps `linkedin_posts.promoted_at` — project-scoped, feeds only that same project's own future prompts. It must NEVER write to the shared, cross-tenant `linkedin_post_examples` table: a self-reported click is not a trustworthy cross-tenant signal (poisoning vector — any user in any org could inject arbitrary text into every other tenant's prompt). Only two things may write to `linkedin_post_examples`: a superadmin's manual add, and `App\Actions\FetchLinkedinPostStats`'s real, externally-measured like count crossing the configured threshold. If a future feature wants to feed the shared pool from user action, it needs its own trust boundary — do not repurpose the promote button.
