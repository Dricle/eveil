<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The project switcher. Which project you are working on is session state, not
 * a URL, so switching returns you to the page you were on.
 */
class CurrentProjectController extends Controller
{
    public function update(Request $request, Project $project): RedirectResponse
    {
        $request->session()->put('current_project_id', $project->id);

        return back();
    }
}
