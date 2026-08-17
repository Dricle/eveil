<?php

namespace App\Http\Middleware;

use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Support\CurrentProject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Inertia\Middleware;
use Laravel\Fortify\Features;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            // The sidebar switcher is on every authenticated page, so both of
            // these are shared rather than passed by each controller.
            //
            // Closures, not values: this middleware is in the `web` group and
            // so runs BEFORE the route middleware that picks the project.
            // Resolving here would read the project as it was one request ago.
            'currentProject' => function () {
                $project = app(CurrentProject::class)->get();

                return $project === null ? null : ProjectResource::make($project);
            },
            'projects' => fn (): array|ResourceCollection => $user === null
                ? []
                : ProjectResource::collection(Project::visibleTo($user)->orderBy('name')->get()),
            // A URL rather than a flag: with sign-ups closed the route is not
            // registered at all, so neither Wayfinder nor `route()` can name
            // it and pages have nothing to link to.
            'registerUrl' => Features::enabled(Features::registration()) ? route('register') : null,
            // One flashed sentence, for actions whose result is not visible on
            // the page they return to — a saved key, a provider that answered.
            'status' => fn (): ?string => $request->session()->get('status'),
        ];
    }
}
