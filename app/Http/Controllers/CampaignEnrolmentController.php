<?php

namespace App\Http\Controllers;

use App\Actions\EnrolCampaign;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Putting the people found since into a sequence that is already running, by
 * hand.
 *
 * A campaign only looks for people twice by itself: when it is activated, and
 * on the `eveil:enrol-due` tick, which deliberately skips a supervised project
 * because there the user decides when. Approving a batch of companies after
 * activation therefore leaves a live campaign reading "nobody in it yet" with
 * no way out of it but toggling the campaign off and on, which is worse: a
 * pause loses nothing but reads as an incident.
 *
 * Active only. Enrolling activates the campaign as a side effect, so on a draft
 * this button would be a send button wearing another name.
 */
class CampaignEnrolmentController extends Controller
{
    public function store(Request $request, int $campaign, EnrolCampaign $enrol): RedirectResponse
    {
        $campaign = Campaign::query()->findOrFail($campaign);

        if ($campaign->status !== CampaignStatus::Active) {
            return back()->with('status', 'That campaign is not running, so nobody was added. Activate it and the people are enrolled with it.');
        }

        $enrolled = $enrol->handle($campaign);

        return back()->with('status', $enrolled === 0
            ? 'Nobody new could be added. Everyone reachable is either already in a sequence, unapproved, suppressed, or the project has no mailbox attached.'
            : "Added {$enrolled} person(s) to {$campaign->name}.");
    }
}
