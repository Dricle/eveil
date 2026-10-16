<?php

namespace App\Http\Controllers;

use App\Actions\DecideAdDecision;
use App\Http\Requests\AdDecisionRequest;
use App\Models\AdDecision;
use Illuminate\Http\RedirectResponse;

/**
 * Approving or rejecting a decision that waited for a person.
 */
class AdDecisionController extends Controller
{
    public function update(AdDecisionRequest $request, int $adDecision, DecideAdDecision $decide): RedirectResponse
    {
        $decision = AdDecision::query()->findOrFail($adDecision);

        if ($request->validated('decision') === 'approve') {
            $decide->approve($decision, $request->user());

            return back()->with('status', 'Approved. It is being applied.');
        }

        $decide->reject($decision, $request->user(), $request->validated('reason'));

        return back()->with('status', 'Rejected. The agent will read why at its next review.');
    }
}
