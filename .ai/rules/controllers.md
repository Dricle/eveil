---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Controllers hold resource actions only. No private methods
A controller contains only the resource actions (`index`, `create`, `store`, `show`, `edit`, `update`, `destroy`) and nothing else. No private helpers, ever. If a method wants extracting, it belongs somewhere with a name:

- validation and input normalisation → a Form Request (`app/Http/Requests/`), with `prepareForValidation()` for the normalising
- authorisation → a Policy (`app/Policies/`)
- a reusable query → an Eloquent scope on the model (`#[Scope]`)
- shaping a model for the front end → an API Resource (`app/Http/Resources/`, see `.ai/rules/resources.md`)
- a use case → `app/Actions/`, the machinery it drives → `app/Services/<Domain>/`
- a pure helper with no domain → `app/Support/`

Order trap found doing this: a Form Request resolves BEFORE any `Gate::authorize()` written inside the controller action, so validation runs first. On a route where a validation rule reaches the network: `ReachableUrl` fetches the URL. That lets an unauthorised caller have the app fetch an address of their choosing on the way to a 404. Authorise with route middleware instead: `Route::resource(...)->middlewareFor('update', 'can:update,project')`, which runs before the request is validated.

Policies here deny with `Response::denyAsNotFound()` rather than a plain `false`: a record in another organization must not confirm that it exists.

## Do not re-check what the middleware already guarantees
Behind the `auth` middleware in `routes/app.php`, `$request->user()` is never null: write `Project::visibleTo($request->user())`, not `$request->user() ?? abort(403)`. Larastan types it as a non-null `App\Models\User`, so the guard does not even buy static-analysis silence; it is dead code that reads as if the route might be public.

Same rule for anything else the route already states: no re-checking the guard, the verified email, or the `can:` middleware from inside the action.

## Never route-model-bind a project-scoped model
`SubstituteBindings` runs in the `web` group, which is BEFORE the route middleware `project.set`. So a model type-hinted into an action is fetched while `CurrentProject` is still unset, the `BelongsToProject` global scope no-ops, and any id in the table resolves: a delete route bound this way removes another project's row and returns 302 instead of 404. Verified on `settings.target-profiles.destroy`.

Take the id (`int $targetProfile`) and look it up in the action instead: `TargetProfile::query()->findOrFail($id)` runs after the middleware, so the scope applies and a foreign id is a genuine 404. `Project` itself is exempt: it carries no `project_id` and the switcher route guards with `can:view,project`.

Every scoped resource needs a test posting another project's id and asserting 404 plus an unchanged row count.
