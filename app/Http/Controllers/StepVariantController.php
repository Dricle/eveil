<?php

namespace App\Http\Controllers;

use App\Http\Requests\StepVariantRequest;
use App\Models\Campaign;
use Illuminate\Http\RedirectResponse;

/**
 * A step's mail is one variant among possibly several: this is where a second
 * wording is added to A/B test against the first, edited, or removed once the
 * test is settled.
 */
class StepVariantController extends Controller
{
    public function store(StepVariantRequest $request, int $campaign, int $step): RedirectResponse
    {
        $campaign = Campaign::query()->findOrFail($campaign);
        $step = $campaign->steps()->findOrFail($step);

        $step->variants()->create($request->columns());

        return back();
    }

    public function update(StepVariantRequest $request, int $campaign, int $step, int $variant): RedirectResponse
    {
        $campaign = Campaign::query()->findOrFail($campaign);
        $step = $campaign->steps()->findOrFail($step);

        $step->variants()->findOrFail($variant)->update($request->columns());

        return back();
    }

    public function destroy(int $campaign, int $step, int $variant): RedirectResponse
    {
        $campaign = Campaign::query()->findOrFail($campaign);
        $step = $campaign->steps()->findOrFail($step);

        // A step with nothing left to send is not a step: the last variant is
        // never removable, only replaced by editing it.
        if ($step->variants()->count() <= 1) {
            return back()->withErrors(['variant' => 'A step needs at least one mail.']);
        }

        $step->variants()->findOrFail($variant)->delete();

        return back();
    }
}
