<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Jobs\AnalyzeProject;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A project is one product to promote. Two fields create one — a name and the
 * address of its site — and everything else about it is derived from there.
 */
class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('projects/Index', [
            'projects' => ProjectResource::collection(
                Project::visibleTo($request->user())
                    ->orderBy('name')
                    ->get()
            ),
        ]);
    }

    public function store(ProjectRequest $request): RedirectResponse
    {
        // No organization picker until multi-organization lands; a user always
        // owns one, created with their account.
        $project = $request->user()->organizations()->firstOrFail()
            ->projects()->create($request->validated());

        AnalyzeProject::dispatch($project);

        return to_route('projects.index');
    }

    public function update(ProjectRequest $request, Project $project): RedirectResponse
    {
        $project->fill($request->validated());

        // A rename says nothing new about the site; a new address is a
        // different site, and the knowledge base built from the old one is now
        // describing a product we are no longer promoting.
        $addressChanged = $project->isDirty('url');

        $project->save();

        if ($addressChanged) {
            AnalyzeProject::dispatch($project);
        }

        return to_route('projects.index');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $project->delete();

        return to_route('projects.index');
    }
}
