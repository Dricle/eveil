<?php

namespace App\Http\Middleware;

use App\Models\Project;
use App\Support\CurrentProject;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Everything the app does happens inside a project, named in the URL
 * (`{project:slug}`) rather than carried in session: a save must only ever
 * be able to reach the project the loaded page actually shows, even when a
 * different tab or a stale session disagrees. This is the one place HTTP
 * sets `CurrentProject`, which is what makes the `BelongsToProject` scope
 * constrain queries built from untrusted input.
 */
class SetCurrentProject
{
    public function __construct(private CurrentProject $currentProject) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Project $project */
        $project = $request->route('project');

        // Denied as a 404 rather than falling back to some other project the
        // user can see: a URL naming a project you cannot reach must not
        // silently resolve to a different one, which is exactly the bug this
        // middleware exists to prevent.
        Gate::authorize('view', $project);

        $this->currentProject->set($project);

        // A "last visited" hint only, read back solely by `AppHomeController`
        // to pick a default when landing on the bare `/app` entry point -
        // never re-read here as authority over which project this request acts on.
        $request->session()->put('current_project_id', $project->id);

        // Keyed `project:slug`, not `project`: the route uses `{project:slug}`,
        // and Laravel only recognises a default for a custom-keyed implicit
        // binding under `<name>:<bindingField>` - a plain `project` key here
        // is silently never matched, and every route call passing a second
        // positional parameter (`route('companies.status', $company)`) then
        // consumes it for `project` instead of `company`.
        URL::defaults(['project:slug' => $project->slug]);

        // Consumed, not left on the route: every controller behind
        // `{project:slug}` was written for a world where its own resource
        // (`Company $company`, `int $campaign`, ...) was the ONLY route
        // parameter, and `ControllerDispatcher` ultimately binds arguments by
        // POSITION (`Controller::callAction()` does `array_values($parameters)`
        // right before the call). Leaving `project` in `$route->parameters()`
        // would shift every later parameter one slot to the right and hand
        // the controller a `Project` where it expects its own model - this is
        // what keeps every existing action signature correct unchanged.
        $request->route()->forgetParameter('project');

        return $next($request);
    }
}
