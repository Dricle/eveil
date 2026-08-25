<?php

namespace App\Http\Controllers;

use App\Actions\CreateOrganization;
use App\Http\Requests\OrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Creating an additional organization (the first is never created here,
 * `App\Actions\CreateAccount` makes it together with the account at signup —
 * no checkout at creation, unlike some references: trial-first, a trial
 * balance is granted and checkout is a button in Billing settings once it
 * runs out, not a forced next step) and the current one's own General
 * settings screen — a rename, nothing else, same reasoning as
 * `ProjectController` handling both a project's creation and its settings.
 */
class OrganizationController extends Controller
{
    public function __construct(private CurrentProject $currentProject) {}

    public function create(): Response
    {
        return Inertia::render('organizations/Create');
    }

    public function store(OrganizationRequest $request, CreateOrganization $createOrganization): RedirectResponse
    {
        $organization = $createOrganization->handle($request->validated('name'), $request->user());

        return to_route('projects.create', ['organization_id' => $organization->id]);
    }

    public function edit(): Response
    {
        return Inertia::render('settings/OrganizationGeneral', [
            'organization' => OrganizationResource::make($this->currentProject->organization()),
        ]);
    }

    public function update(OrganizationRequest $request): RedirectResponse
    {
        $this->currentProject->organization()->update($request->validated());

        return to_route('settings.organization.general.edit');
    }
}
