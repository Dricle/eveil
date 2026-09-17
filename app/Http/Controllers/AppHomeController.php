<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The bare `/app` entry point: nothing here is project-scoped, so this picks
 * a project to land in rather than rendering anything itself. Every other
 * page's project comes from its own URL; this is the one place that still
 * reads `current_project_id` from session, and only as a "last visited" hint.
 */
class AppHomeController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        $hint = (int) $request->session()->get('current_project_id');

        $project = Project::visibleTo($request->user())->whereKey($hint)->first()
            ?? Project::visibleTo($request->user())->orderBy('name')->first();

        return $project === null
            ? to_route('projects.create')
            : to_route('dashboard', ['project' => $project->slug]);
    }
}
