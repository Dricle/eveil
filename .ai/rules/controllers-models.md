---
paths:
  - 'routes/app.php,app/Http/Controllers/Linkedin*OAuthController.php,app/Models/Project.php'
---

# Controllers Models

## OAuth redirect_uris live under a fixed top-level `oauth/` segment, never `{project:slug}`
Every provider's redirect_uri (LinkedIn's two apps today) is registered as one static URL on the provider's side, so it CANNOT carry `{project:slug}` - that would make it a different URL per project and the provider only whitelists one value. All such callbacks live grouped under `Route::prefix('oauth')->name('oauth.')` in routes/app.php, OUTSIDE the `{project:slug}` group: `oauth/linkedin/callback` (`oauth.linkedin.callback`), `oauth/linkedin/stats/callback` (`oauth.linkedin.stats.callback`). `oauth` is reserved in `Project::RESERVED_SLUGS`.

Since these routes never run `project.set`, `CurrentProject` is unset in the callback. Each `redirect()` action (still reached from inside a project, so `CurrentProject` works there) stores the project id in session (e.g. `linkedin_oauth_project_id`); the callback pulls it back, loads the `Project`, and passes it explicitly as `['project' => $project]` to any `route()`/`to_route()` call that needs the `{project:slug}` segment - `URL::defaults()` is never set on this request. A missing/unresolvable project redirects to `app.home`; a resolvable project but failed state redirects to `settings.linkedin.index` with that project so the user lands back where they started.

A future provider integration (Google, Slack, etc.) should add its callback under this same `oauth/` group rather than inventing its own reserved top-level slug.
