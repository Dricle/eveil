---
paths:
  - 'app/Http/Resources/**'
---

# Resources

## Inertia props go through an API Resource, unwrapped
Shaping a model for the front end is a Resource in `app/Http/Resources/`, never a `->map(fn ($model) => [...])` in the controller action. Give the class a `/** @mixin App\Models\Thing */` docblock so Larastan resolves `$this->column`.

`JsonResource::withoutWrapping()` is set in `AppServiceProvider::configureDefaults()` and must stay. Inertia resolves a `JsonResource` prop by calling `toResponse($request)`, which applies the `data` envelope — without the call, `ProjectResource::collection(...)` arrives on the page as `projects.data` instead of `projects`, and every page reading a collection has to know it.

Query with a plain `->get()`. Do not hand it a column list to match the resource — the two then have to be kept in step, and a resource reading a column the query forgot fails at render rather than at the call site. The resource is what decides which columns leave the server: `projects.knowledge_base` is loaded and deliberately never sent, only turned into an `analyzed` boolean.
