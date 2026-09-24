<?php

namespace App\Http\Controllers;

use App\Http\Requests\AutonomyRequest;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * How much each channel does on its own, one setting per channel rather than
 * one for the whole project: emailing a company nobody looked at and
 * publishing under someone's name are not the same risk, so a user can hand
 * one over without the other. Reddit has no setting, it never posts on its
 * own.
 */
class AutonomyController extends Controller
{
    public function edit(CurrentProject $currentProject): Response
    {
        $project = $currentProject->getOrFail();

        return Inertia::render('settings/Autonomy', [
            'emailAutonomyLevel' => $project->email_autonomy_level->value,
            'linkedinAutonomyLevel' => $project->linkedin_autonomy_level->value,
        ]);
    }

    public function update(AutonomyRequest $request, CurrentProject $currentProject): RedirectResponse
    {
        $currentProject->getOrFail()->update($request->validated());

        return to_route('settings.autonomy.edit');
    }
}
