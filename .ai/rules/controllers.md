---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Controllers hold resource actions only — no private methods
A controller contains only the resource actions (`index`, `create`, `store`, `show`, `edit`, `update`, `destroy`) and nothing else. No private helpers, ever. If a method wants extracting, it belongs somewhere with a name:

- validation and input normalisation → a Form Request (`app/Http/Requests/`), with `prepareForValidation()` for the normalising
- authorisation → a Policy (`app/Policies/`)
- a reusable query → an Eloquent scope on the model (`#[Scope]`)
- shaping a model for the front end → an API Resource (`app/Http/Resources/`, see `.ai/rules/resources.md`)
- a use case → `app/Actions/`, the machinery it drives → `app/Services/<Domain>/`
- a pure helper with no domain → `app/Support/`

Order trap found doing this: a Form Request resolves BEFORE any `Gate::authorize()` written inside the controller action, so validation runs first. On a route where a validation rule reaches the network — `ReachableUrl` fetches the URL — that lets an unauthorised caller have the app fetch an address of their choosing on the way to a 404. Authorise with route middleware instead: `Route::resource(...)->middlewareFor('update', 'can:update,project')`, which runs before the request is validated.

Policies here deny with `Response::denyAsNotFound()` rather than a plain `false`: a record in another organization must not confirm that it exists.

## Do not re-check what the middleware already guarantees
Behind the `auth` middleware in `routes/app.php`, `$request->user()` is never null — write `Project::visibleTo($request->user())`, not `$request->user() ?? abort(403)`. Larastan types it as a non-null `App\Models\User`, so the guard does not even buy static-analysis silence; it is dead code that reads as if the route might be public.

Same rule for anything else the route already states: no re-checking the guard, the verified email, or the `can:` middleware from inside the action.
