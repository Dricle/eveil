<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CampaignResource;
use App\Models\Campaign;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The sequences, with the same counts the app's list shows. The people in
 * one are `CampaignLeadController`, paginated on their own.
 */
class CampaignController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CampaignResource::collection(
            Campaign::query()
                ->with('targetProfile')
                ->withDeliveryCounts()
                ->orderBy('id')
                ->paginate(50)
        );
    }

    public function show(int $campaign): CampaignResource
    {
        return CampaignResource::make(
            Campaign::query()
                ->with(['targetProfile', 'steps.variants'])
                ->withDeliveryCounts()
                ->findOrFail($campaign)
        );
    }
}
