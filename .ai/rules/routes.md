---
paths:
  - 'routes/**'
---

# Routes

## The app opens inside a project: current project is session state, never a URL segment
Everything below the dashboard belongs to one project, so the project is context rather than navigation. It is chosen once in the sidebar switcher and lives in the session — no page under `/app` carries a `{project}` segment, which is what lets switching projects keep you on the page you were on.

Two middlewares, aliased in `bootstrap/app.php`:
- `project.set` (`SetCurrentProject`) — on the whole `auth` group. Re-reads the session id through `Project::visibleTo($user)` EVERY request (access can be revoked while a session is open, and the session is the user's to tamper with), falls back to the first readable project, and always assigns `CurrentProject` — null included, because it is a singleton and last request's project left in it is how one user's page gets built from another's data. This is also the one place HTTP sets `CurrentProject`, which is what makes the `BelongsToProject` scope constrain queries.
- `project.require` (`RequireCurrentProject`) — only on pages that mean nothing without one; sends a projectless user to `projects.create`. Deliberately NOT on `/app/account/*`: somebody with no project still has an account.

Controllers behind `project.require` read `CurrentProject::getOrFail()`, which throws rather than returning null — that is what lets them be typed without re-testing what the middleware guarantees.

Trap: `HandleInertiaRequests` is in the `web` group and therefore runs BEFORE route middleware, so a shared prop that reads `CurrentProject` must be a CLOSURE. A plain value resolves one request too early and serves the previous request's project.

Project settings live under `/app/settings/*` (`settings.project.*`, `settings.knowledge-base.*`), not `/app/projects/{id}/...`. The only route still taking a project id is the switcher itself, `PUT /app/current-project/{project}`, guarded by `can:view,project`.
