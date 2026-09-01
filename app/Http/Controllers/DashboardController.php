<?php

namespace App\Http\Controllers;

use App\Actions\SummarizeRunningDiscovery;
use App\Cloud\Models\CreditTransaction;
use App\Enums\MessageDirection;
use App\Enums\OutreachStatus;
use App\Enums\ReplyClassification;
use App\Http\Resources\CampaignResource;
use App\Http\Resources\MailboxResource;
use App\Http\Resources\ReplyResource;
use App\Models\AgentRun;
use App\Models\Campaign;
use App\Models\CampaignLead;
use App\Models\Company;
use App\Models\DiscoveryRun;
use App\Models\Lead;
use App\Models\Message;
use App\Support\CurrentProject;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What this project has actually produced, and what wants a person today.
 *
 * The headline number is the POSITIVE reply rate, not the raw one: a raw rate
 * counts "no thanks" and out-of-office alongside real interest, and a dashboard
 * that flatters is worse than none. Sends and replies are counted from
 * `messages` rather than from campaign state, because a mail that left is a fact
 * and a status is a summary.
 */
class DashboardController extends Controller
{
    public function __construct(
        private CurrentProject $currentProject,
        private SummarizeRunningDiscovery $summarizeRunningDiscovery,
    ) {}

    public function index(Request $request): Response
    {
        $sent = Message::query()->where('direction', MessageDirection::Outbound)->whereNotNull('sent_at')->count();
        $replies = Message::query()->where('direction', MessageDirection::Inbound)->count();
        $positive = Message::query()
            ->where('direction', MessageDirection::Inbound)
            ->where('classification', ReplyClassification::Interested)
            ->count();
        $awaitingHuman = CampaignLead::query()
            ->whereHas('campaign')
            ->where('pause_reason', 'awaiting_human')
            ->count();

        return Inertia::render('Dashboard', [
            // Somebody who wandered off mid-setup needs the way back: until a
            // search has run there is nothing on this page but zeroes, and a
            // dashboard of zeroes reads as a product that does not work.
            'onboarding' => DiscoveryRun::query()->doesntExist(),
            'greeting' => [
                'name' => explode(' ', (string) $request->user()?->name)[0],
                'days_running' => (int) $this->currentProject->get()?->created_at?->diffInDays(now()),
            ],
            'stats' => [
                'companies_found' => Company::query()->count(),
                'companies_kept' => Company::query()->contactable()->count(),
                'sent' => $sent,
                'replies' => $replies,
                'positive' => $positive,
                'positive_rate' => $sent === 0 ? null : (int) round($positive / $sent * 100),
                'awaiting_human' => $awaitingHuman,
                // Tokens on self-hosted, credits spent on cloud - never both,
                // and never a conversion between them: a self-hosted operator
                // pays their own provider and wants token counts; a cloud
                // customer is billed in credits and must never see a token
                // count or a model name, whatever it would convert to.
                ...(config('eveil.edition') === 'cloud'
                    ? [
                        'credits_spent' => abs((int) CreditTransaction::query()
                            ->where('type', 'debit')
                            ->whereHas('agentRun')
                            ->sum('credits')),
                    ]
                    : [
                        'tokens_in' => (int) AgentRun::query()->sum('tokens_in'),
                        'tokens_out' => (int) AgentRun::query()->sum('tokens_out'),
                    ]),
            ],
            // The funnel: how far the people in sequences have actually got.
            // Not drawn on the redesigned page - the running-discovery and
            // campaigns cards say more - but kept on the wire for whatever
            // reads this prop next.
            'pipeline' => CampaignLead::query()
                ->whereHas('campaign')
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'autonomyLevel' => $this->currentProject->getOrFail()->autonomy_level,
            // Found by discovery, and not yet queued into a sequence: the
            // person who reviews them decides whether they belong in one.
            'newLeadsCount' => Lead::query()->contactable()->where('status', OutreachStatus::New)->count(),
            'runningDiscoveryRun' => $this->summarizeRunningDiscovery->handle(),
            'campaigns' => CampaignResource::collection(
                Campaign::query()
                    ->withCount([
                        'campaignLeads',
                        'steps',
                        'campaignLeads as sent_leads_count' => fn ($leads) => $leads
                            ->whereHas('messages', fn ($messages) => $messages->where('direction', MessageDirection::Outbound)),
                        'campaignLeads as replied_leads_count' => fn ($leads) => $leads
                            ->whereHas('messages', fn ($messages) => $messages->where('direction', MessageDirection::Inbound)),
                    ])
                    ->latest('id')
                    ->limit(5)
                    ->get()
            ),
            // A mailbox belongs to the ORGANIZATION, not the project: only the
            // ones actually attached here are this project's to show.
            'mailboxes' => MailboxResource::collection(
                $this->currentProject->getOrFail()->emailAccounts()->orderBy('from_email')->get()
            ),
            'latestReplies' => ReplyResource::collection(
                Message::query()
                    ->where('direction', MessageDirection::Inbound)
                    ->with(['lead.company'])
                    ->latest('received_at')
                    ->limit(3)
                    ->get()
            ),
        ]);
    }
}
