<?php

namespace App\Http\Controllers\Api;

use App\Actions\EnrolCampaign;
use App\Enums\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;

/**
 * The app's "add the people found since" button: everyone eligible joins the
 * running campaign, under the same rules as activating it. Active campaigns
 * only, as in the app, since enrolling would otherwise start a draft.
 */
class CampaignEnrolmentController extends Controller
{
    public function store(int $campaign, EnrolCampaign $enrol): JsonResponse
    {
        $campaign = Campaign::query()->findOrFail($campaign);

        if ($campaign->status !== CampaignStatus::Active) {
            return response()->json(['message' => 'That campaign is not running. Activate it in the app first.'], 409);
        }

        return response()->json(['enrolled' => $enrol->handle($campaign)]);
    }
}
