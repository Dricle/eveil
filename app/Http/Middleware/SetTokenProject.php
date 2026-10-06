<?php

namespace App\Http\Middleware;

use App\Models\Project;
use App\Support\CurrentProject;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The API's `SetCurrentProject`. An API token belongs to one project (the
 * project is the token's owner, not a user), so the authenticated "user" of
 * an API request IS the project it may act on. Nothing in the URL names a
 * project: a token cannot be pointed at another one, which is what keeps the
 * `BelongsToProject` scope the only thing a query needs.
 */
class SetTokenProject
{
    public function __construct(private CurrentProject $currentProject) {}

    public function handle(Request $request, Closure $next): Response
    {
        $project = Auth::guard('sanctum')->user();

        abort_unless($project instanceof Project, 401);

        $this->currentProject->set($project);

        return $next($request);
    }
}
