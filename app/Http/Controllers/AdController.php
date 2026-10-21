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
 * The ads screen: the project's monthly budget and what each account spends
 * of it, the campaigns
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

        $accounts = $project->adAccounts()->orderBy('id')->get();

        return Inertia::render('ads/Index', [
            'budget' => [
                'monthly' => $project->ads_monthly_budget_micros / 1_000_000,
                'currency' => $project->ads_currency,
                'spent_this_month' => $project->adsSpentThisMonthMicros() / 1_000_000,
                'active_daily_budget' => $project->adsActiveDailyBudgetMicros() / 1_000_000,
                // The currencies the budget can be set in: the connected
                // accounts' own, since budgets are never converted.
                'currencies' => $accounts->pluck('currency')->unique()->values()->all(),
            ],
            'accounts' => AdAccountResource::collection($accounts),
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
                'configured' => PlatformApp::resolve($organization, $platform->appKind()) !== null,
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
