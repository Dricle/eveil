---
paths:
  - 'app/Http/Controllers/TargetProfileController.php,app/Http/Controllers/TargetProfileSubredditsController.php,app/Ai/Tools/CreateTargetProfile.php,app/Ai/Tools/UpdateTargetProfile.php'
---

# Ai Tools

## Only DeriveTargetProfiles ever called FindSubreddits - manual/Evie creation never did
Confirmed on prod DB (2026-09-18): profiles created by hand (`TargetProfileController::store`, the "New profile" form) or by Evie (`CreateTargetProfile` tool) never had `criteria.subreddits` resolved - both skip `FindSubreddits` entirely. Only `DeriveTargetProfiles::store()` (the batch AI-derivation flow) calls it. `FindSubreddits::topics()` already falls back to `criteria.sectors` when `subreddit_topics` is absent, so it works fine for these profiles too once triggered - it was just never triggered.

Fixed with a manual trigger, not by wiring FindSubreddits into creation: `TargetProfileSubredditsController::store` (route `targets.subreddits`) calls `FindSubreddits::handle($profile)` synchronously (mechanical/no-AI, a few seconds, unlike the queued `DeriveTargets` job a real agent call needs) and is exposed as a "Find subreddits" / "Re-check" button in `targets/Profile.vue`'s Reddit communities field. Deliberately NOT a free-text edit field: the whole point of `SubredditFinder` is verifying a name is real before it's trusted, and manual typing would reintroduce exactly that risk.

Separate thing noticed investigating this: [[discovery-ai-agents]] section on `UpdateTargetProfile`/`TargetProfileController::update` - editing ANY field on a profile unconditionally flips `source` to `human`, even a pure rename of an agent-derived profile. `criteria` is merged not replaced, so `subreddits`/`confidence`/`subreddit_topics` survive the flip - explains why some `source=human` rows on prod still carry full agent-shaped criteria. Working as designed (`.ai/rules/routes.md`/`controllers.md` territory: "a corrected profile is the user's from now on"), not a bug, but easy to misread as one when auditing rows by `source` alone.

Trap hit shipping the route: adding a `use App\Http\Controllers\X;` import in one Edit call, then its actual usage (`X::class` in `Route::post(...)`) in a SEPARATE later Edit call, lets Pint's dirty-file formatter strip the import as unused in the intermediate state - same gotcha `.ai/rules/ai.md` already documents for agent classes, confirmed here for `routes/app.php` too. Symptom: `php artisan route:list` throws `ReflectionException: Class "X" does not exist` (no namespace prefix in the message - that's the tell), and PHPStan reports `class.notFound` on the route file even though the controller file itself analyses clean in isolation. Add the import and its usage in the SAME Edit, or re-check the import survived before moving on.
