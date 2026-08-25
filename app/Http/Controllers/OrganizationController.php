<?php

namespace App\Http\Controllers;

use App\Cloud\Actions\GrantTrialCredits;
use App\Enums\OrganizationRole;
use App\Http\Requests\OrganizationRequest;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A second (or third) organization for a user who already has one — the
 * first is never created here, `App\Actions\CreateAccount` makes it together
 * with the account at signup. No checkout at creation, unlike some
 * references: trial-first — a trial balance is granted and checkout is a
 * button in Billing settings once it runs out, not a forced next step.
 */
class OrganizationController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('organizations/Create');
    }

    public function store(OrganizationRequest $request, GrantTrialCredits $grantTrialCredits): RedirectResponse
    {
        $organization = Organization::create($request->validated());

        $organization->users()->attach($request->user(), ['role' => OrganizationRole::Owner->value]);

        if (config('eveil.edition') === 'cloud') {
            $grantTrialCredits->handle($organization);
        }

        return to_route('projects.create', ['organization_id' => $organization->id]);
    }
}
