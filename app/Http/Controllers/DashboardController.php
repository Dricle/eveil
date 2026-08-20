<?php

namespace App\Http\Controllers;

use App\Enums\CampaignStatus;
use App\Enums\MessageDirection;
use App\Enums\ReplyClassification;
use App\Models\AgentRun;
use App\Models\Campaign;
use App\Models\CampaignLead;
use App\Models\Company;
use App\Models\DiscoveryRun;
use App\Models\Lead;
use App\Models\Message;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What this project has actually produced.
 *
 * The headline number is the POSITIVE reply rate, not the raw one: a raw rate
 * counts "no thanks" and out-of-office alongside real interest, and a dashboard
 * that flatters is worse than none. Sends and replies are counted from
 * `messages` rather than from campaign state, because a mail that left is a fact
 * and a status is a summary.
 *
 * Token counts, never money: no provider reports a price, so a figure in euros
 * would be our own arithmetic against a number that drifts. Wrong quietly, in
 * a column that looks authoritative.
 */
class DashboardController extends Controller
{
    public function index(): Response
    {
        $sent = Message::query()->where('direction', MessageDirection::Outbound)->whereNotNull('sent_at')->count();
        $replies = Message::query()->where('direction', MessageDirection::Inbound)->count();
        $positive = Message::query()
            ->where('direction', MessageDirection::Inbound)
            ->where('classification', ReplyClassification::Interested)
            ->count();

        return Inertia::render('Dashboard', [
            // Somebody who wandered off mid-setup needs the way back: until a
            // search has run there is nothing on this page but zeroes, and a
            // dashboard of zeroes reads as a product that does not work.
            'onboarding' => DiscoveryRun::query()->doesntExist(),
            'stats' => [
                'companies' => Company::query()->contactable()->count(),
                'contacts' => Lead::query()->contactable()->count(),
                'active_campaigns' => Campaign::query()->where('status', CampaignStatus::Active)->count(),
                'sent' => $sent,
                'replies' => $replies,
                'positive' => $positive,
                // Rounded to a whole percent: a tenth of a percent on 30 sends
                // is noise dressed as precision.
                'positive_rate' => $sent === 0 ? null : (int) round($positive / $sent * 100),
                'awaiting_human' => CampaignLead::query()
                    ->whereHas('campaign')
                    ->where('pause_reason', 'awaiting_human')
                    ->count(),
                'tokens_in' => (int) AgentRun::query()->sum('tokens_in'),
                'tokens_out' => (int) AgentRun::query()->sum('tokens_out'),
            ],
            // The funnel: how far the people in sequences have actually got.
            'pipeline' => CampaignLead::query()
                ->whereHas('campaign')
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'recent' => AgentRun::query()
                ->latest('id')
                ->limit(8)
                ->get(['id', 'agent', 'status', 'created_at'])
                ->map(fn (AgentRun $run): array => [
                    'id' => $run->id,
                    'agent' => $run->agent,
                    'status' => $run->status->value,
                    'at' => $run->created_at?->toIso8601String(),
                ]),
        ]);
    }
}
