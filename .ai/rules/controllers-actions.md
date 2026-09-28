---
paths:
  - 'app/Models/SocialPost.php,app/Models/SocialPostExample.php,app/Http/Controllers/SocialPostController.php,app/Actions/FetchSocialPostStats.php'
---

# Controllers Actions

## The two proven-post pools trust different signals — never mix them
A user clicking "mark as successful" (`SocialPostController::promote()`) ONLY ever stamps `social_posts.promoted_at` — project-scoped, feeds only that same project's own future prompts. It must NEVER write to the shared, cross-tenant `social_post_examples` table: a self-reported click is not a trustworthy cross-tenant signal (poisoning vector — any user in any org could inject arbitrary text into every other tenant's prompt). Only two things may write to `social_post_examples`: a superadmin's manual add, and `App\Actions\FetchSocialPostStats`'s real, externally-measured like count crossing that network's threshold (`SocialPlatform::minLikesSetting()`; X has none, so its bank only grows by hand). If a future feature wants to feed the shared pool from user action, it needs its own trust boundary — do not repurpose the promote button.
