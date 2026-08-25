<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectRequest;
use App\Http\Resources\ProjectDetailResource;
use App\Jobs\AnalyzeProject;
use App\Support\CurrentProject;
use App\Support\Settings;
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
    public function __construct(private CurrentProject $currentProject, private Settings $settings) {}

    public function create(Request $request): Response
    {
        return Inertia::render('projects/Create', [
            // Only ever present right after `organizations/Create`'s
            // redirect: the brand new organization this project should join,
            // which nothing in the session can name yet.
            'organizationId' => $request->integer('organization_id') ?: null,
        ]);
    }

    public function store(ProjectRequest $request): RedirectResponse
    {
        $organization = $request->organization($this->currentProject);
        $data = $request->safe()->except('organization_id');

        // The second trial guard, next to the credit spend: a cap on leads
        // DISCOVERED, not chosen by the user, so a trial organization cannot
        // opt out of it by leaving the field blank.
        // Reuses `lead_limit`, which continuous discovery already respects,
        // rather than a second column meaning the same thing.
        if ($organization->isOnTrial()) {
            $data['lead_limit'] = $this->settings->int('billing.trial_lead_limit');
        }

        $project = $organization->projects()->create($data);

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
