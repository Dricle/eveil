<?php

namespace App\Http\Middleware;

use App\Models\Project;
use App\Support\CurrentProject;
use App\Support\TokenScope;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The API's `SetCurrentProject`: the `{project:slug}` in the URL, among the
 * projects the token reaches (`TokenScope`, set by `SetTokenScope` before
 * this). Another organization's project is a 404, exactly like a slug that
 * does not exist, so a token cannot even confirm it is there.
 *
 * Then the `BelongsToProject` scope is the only thing a query needs, and the
 * parameter is forgotten so the controllers' own ids keep their position
 * (see `.ai/rules/routes.md`).
 */
class SetTokenProject
{
    public function __construct(private CurrentProject $currentProject) {}

    public function handle(Request $request, Closure $next): Response
    {
        $project = $request->route('project');

        abort_unless($project instanceof Project && app(TokenScope::class)->projects()->whereKey($project->id)->exists(), 404);

        $this->currentProject->set($project);
        $request->route()->forgetParameter('project');

        return $next($request);
    }
}
