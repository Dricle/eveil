<?php

namespace App\Http\Controllers;

use App\Ai\Agents\AdStrategist;
use App\Enums\AdPlatform;
use App\Enums\AdStatus;
use App\Http\Resources\AdAccountResource;
use App\Http\Resources\AdCampaignResource;
use App\Http\Resources\AdDecisionResource;
use App\Models\AdCampaign;
use App\Models\AgentRun;
use App\Models\PlatformApp;
use App\Support\CurrentProject;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The ads screen: what each account spends against its month, the campaigns
 * Eveil runs with their ads and numbers, and the decision feed, with the
 * ones waiting for a person on top. A nav item, not a settings page: it
 * changes every day on its own.
 */
class AdController extends Controller
{
    public function index(CurrentProject $currentProject): Response
    {
        $project = $currentProject->getOrFail();
        $organization = $project->organization;

        return Inertia::render('ads/Index', [
            'accounts' => AdAccountResource::collection($project->adAccounts()->orderBy('id')->get()),
            'campaigns' => AdCampaignResource::collection(
                AdCampaign::query()
                    ->where('status', '!=', AdStatus::Removed)
                    ->with(['account', 'ads' => fn ($query) => $query->where('status', '!=', AdStatus::Removed)->with('creative')->withTotals()])
                    ->latest()
                    ->get()
            ),
            'decisions' => AdDecisionResource::collection(
                $project->adDecisions()->with('decider')->orderByRaw("status = 'proposed' desc")->latest()->limit(50)->get()
            ),
            // Which networks can be connected right now: an OAuth app exists
            // for this organization, its own or the instance's.
            'platforms' => array_map(fn (AdPlatform $platform): array => [
                'value' => $platform->value,
                'label' => $platform->label(),
                'configured' => PlatformApp::resolve($organization, $platform) !== null,
            ], AdPlatform::cases()),
            'autonomyLevel' => $project->ads_autonomy_level->value,
            'reviewing' => AgentRun::query()
                ->where('agent', AdStrategist::slug())
                ->latest('id')
                ->first()
                ?->isInFlight() ?? false,
        ]);
    }
}
