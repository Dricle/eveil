<?php

namespace App\Http\Middleware;

use App\Support\CurrentProject;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For the pages that mean nothing without a project. A fresh instance has none,
 * and an empty dashboard would be a worse answer than the screen that creates
 * the first one.
 */
class RequireCurrentProject
{
    public function __construct(private CurrentProject $currentProject) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->currentProject->isSet()) {
            return redirect()->route('projects.create');
        }

        return $next($request);
    }
}
