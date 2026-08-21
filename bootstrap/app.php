<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireCurrentProject;
use App\Http\Middleware\SetCurrentProject;
use App\Http\Middleware\ShareTargetProfiles;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('web')
                ->prefix('app')
                ->group(base_path('routes/app.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Laravel's default list minus `X-Forwarded-Host`: it decides where a
        // user lands after signing in, and the proxy list is `*`.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_PREFIX
            | Request::HEADER_X_FORWARDED_AWS_ELB);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'project.set' => SetCurrentProject::class,
            'project.require' => RequireCurrentProject::class,
            'targets.share' => ShareTargetProfiles::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
