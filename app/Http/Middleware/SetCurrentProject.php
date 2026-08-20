<?php

namespace App\Http\Middleware;

use App\Models\Project;
use App\Support\CurrentProject;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Everything the app does happens inside a project, so the project is picked
 * once here rather than carried in the URL of every page: switching projects
 * then keeps you where you were instead of dropping you at a different address.
 *
 * This is also the one place HTTP sets `CurrentProject`, which is what makes
 * the `BelongsToProject` scope constrain queries built from untrusted input.
 */
class SetCurrentProject
{
    public function __construct(private CurrentProject $currentProject) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $project = null;

        if ($user !== null) {
            // The stored id is re-read through `visibleTo` every request:
            // access can be taken away while a session is still open, and the
            // session is the user's to tamper with.
            $selected = (int) $request->session()->get('current_project_id');

            $project = Project::visibleTo($user)->whereKey($selected)->first()
                ?? Project::visibleTo($user)->orderBy('name')->first();
        }

        if ($project !== null) {
            $request->session()->put('current_project_id', $project->id);
        }

        // Always assigned, null included: `CurrentProject` is a singleton, and
        // leaving the previous request's project in it is how one user's page
        // would be built from another's data.
        $this->currentProject->set($project);

        return $next($request);
    }
}
