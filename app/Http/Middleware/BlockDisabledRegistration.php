<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An instance with sign-ups closed answers 404 on the registration routes, not
 * a form that refuses. Fortify keeps registering them either way, so the route
 * table — and the TypeScript Wayfinder generates from it — stays the same
 * whatever the env file says.
 */
class BlockDisabledRegistration
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(
            $request->routeIs('register', 'register.store') && ! config('eveil.registration_enabled'),
            404,
        );

        return $next($request);
    }
}
