<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CampaignLeadResource;
use App\Models\Campaign;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Everyone in one sequence and where they are in it. The app caps its own
 * view at fifty; a sync needs all of them, so this pages instead.
 */
class CampaignLeadController extends Controller
{
    public function index(int $campaign): AnonymousResourceCollection
    {
        return CampaignLeadResource::collection(
            Campaign::query()->findOrFail($campaign)
                ->campaignLeads()
                ->with('lead.company')
                ->withCount('sentMessages')
                ->orderBy('id')
                ->paginate(50)
        );
    }
}
