<?php

namespace App\Http\Controllers;

use App\Http\Requests\CampaignStepOrderRequest;
use App\Models\Campaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Moving a step. The whole order arrives at once and is rewritten in one
 * transaction: `(campaign_id, position)` is unique, so renumbering row by row
 * collides with itself halfway through.
 *
 * Ids that do not belong to this campaign are simply not found — the relation
 * is the filter, so a foreign id reorders nothing rather than reordering
 * somebody else's sequence.
 */
class CampaignStepOrderController extends Controller
{
    public function update(CampaignStepOrderRequest $request, int $campaign): RedirectResponse
    {
        $campaign = Campaign::query()->findOrFail($campaign);

        /** @var array<int, int> $ordered */
        $ordered = $request->validated('steps');

        DB::transaction(function () use ($campaign, $ordered): void {
            // Out of the way first: positions are unique, and the new order
            // overlaps the old one on nearly every row.
            $campaign->steps()->update(['position' => DB::raw('position + 1000')]);

            foreach (array_values($ordered) as $index => $id) {
                $campaign->steps()->whereKey($id)->update(['position' => $index + 1]);
            }
        });

        return back();
    }
}
