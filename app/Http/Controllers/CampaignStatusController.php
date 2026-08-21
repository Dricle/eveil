<?php

namespace App\Http\Controllers;

use App\Actions\EnrolCampaign;
use App\Enums\CampaignStatus;
use App\Http\Requests\CampaignStatusRequest;
use App\Models\Campaign;
use Illuminate\Http\RedirectResponse;

/**
 * Starting and pausing a sequence, which is the only switch that makes mail
 * leave the building. Its own screen-agnostic endpoint because it is thrown
 * from the campaign list as often as from the campaign itself.
 *
 * The id is looked up here rather than type-hinted: route model binding
 * resolves before the middleware that sets the current project, so a bound
 * model would be fetched while the scope is still inert.
 */
class CampaignStatusController extends Controller
{
    public function __construct(private EnrolCampaign $enrol) {}

    public function update(CampaignStatusRequest $request, int $campaign): RedirectResponse
    {
        $campaign = Campaign::query()->findOrFail($campaign);
        $status = $request->enum('status', CampaignStatus::class);

        // Read before the write: activating is what puts people into the
        // sequence, and until then a campaign is only a document. Enrolling on
        // every save would re-add everybody suppressed or won since.
        $activating = $campaign->status !== CampaignStatus::Active && $status === CampaignStatus::Active;

        $campaign->update(['status' => $status]);

        if ($activating) {
            $this->enrol->handle($campaign);
        }

        return back();
    }
}
