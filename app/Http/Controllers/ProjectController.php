<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectRequest;
use App\Http\Resources\ProjectDetailResource;
use App\Jobs\AnalyzeProject;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A project is one product to promote. Two fields create one: a name and the
 * address of its site, and everything else about it is derived from there.
 *
 * There is no index: the project list lives in the sidebar switcher, and the
 * project being edited is always the current one, from the session.
 */
class ProjectController extends Controller
{
    public function __construct(private CurrentProject $currentProject) {}

    public function create(): Response
    {
        return Inertia::render('projects/Create');
    }

    public function store(ProjectRequest $request): RedirectResponse
    {
        // No organization picker until multi-organization lands; a user always
        // owns one, created with their account.
        $project = $request->user()->organizations()->firstOrFail()
            ->projects()->create($request->validated());

        AnalyzeProject::dispatch($project);

        // Land on what was just created rather than on whatever was selected
        // before. Creating a project is how you say you want to work on it.
        $request->session()->put('current_project_id', $project->id);

        // Straight into the guided run: the site is being read right now, and
        // watching that happen is the whole first impression. A dashboard of
        // zeroes at this moment reads as a product that does nothing.
        return to_route('onboarding');
    }

    public function edit(): Response
    {
        return Inertia::render('settings/Project', [
            'project' => ProjectDetailResource::make(
                $this->currentProject->getOrFail()->load('latestAnalysis')
            ),
        ]);
    }

    public function update(ProjectRequest $request): RedirectResponse
    {
        $project = $this->currentProject->getOrFail();

        $project->fill($request->validated());

        // A rename says nothing new about the site; a new address is a
        // different site, and the knowledge base built from the old one is now
        // describing a product we are no longer promoting.
        $addressChanged = $project->isDirty('url');

        $project->save();

        if ($addressChanged) {
            AnalyzeProject::dispatch($project);
        }

        return to_route('settings.project.edit');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->currentProject->getOrFail()->delete();

        // The next request picks whatever is left, or the create screen.
        $request->session()->forget('current_project_id');

        return to_route('dashboard');
    }
}
