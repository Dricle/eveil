<?php

namespace App\Http\Controllers;

use App\Enums\CampaignStepType;
use App\Http\Requests\CampaignStepRequest;
use App\Models\Campaign;
use App\Models\CampaignStep;
use Illuminate\Http\RedirectResponse;

/**
 * Composing the sequence by hand: the escape hatch for anyone who would rather
 * write their own, and the way every generated step is corrected.
 */
class CampaignStepController extends Controller
{
    public function store(CampaignStepRequest $request, int $campaign): RedirectResponse
    {
        $campaign = Campaign::query()->findOrFail($campaign);
        $step = $campaign->steps()->create([
            ...$request->columns(),
            'position' => ((int) $campaign->steps()->max('position')) + 1,
        ]);

        if ($step->type === CampaignStepType::Email) {
            $step->variants()->create([
                'subject' => (string) $request->validated('subject'),
                'body' => (string) $request->validated('body'),
                'language' => null,
                'weight' => 1,
            ]);
        }

        return back();
    }

    public function update(CampaignStepRequest $request, int $campaign, int $step): RedirectResponse
    {
        $campaign = Campaign::query()->findOrFail($campaign);
        /** @var CampaignStep $step */
        $step = $campaign->steps()->findOrFail($step);

        $step->update($request->columns());

        if ($step->type === CampaignStepType::Email) {
            // One variant per step until A/B exists, so the mail is updated in
            // place rather than piling a second one up behind it.
            $step->variants()->updateOrCreate([], [
                'subject' => (string) $request->validated('subject'),
                'body' => (string) $request->validated('body'),
            ]);
        }

        return back();
    }

    public function destroy(int $campaign, int $step): RedirectResponse
    {
        Campaign::query()->findOrFail($campaign)->steps()->findOrFail($step)->delete();

        return back();
    }
}
