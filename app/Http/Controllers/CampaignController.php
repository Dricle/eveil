<?php

namespace App\Http\Controllers;

use App\Actions\DispatchDueSends;
use App\Actions\PreviewSequence;
use App\Actions\WriteMissingCampaigns;
use App\Ai\Agents\SequenceWriter;
use App\Enums\AgentRunStatus;
use App\Enums\CampaignLeadStatus;
use App\Http\Requests\CampaignRequest;
use App\Http\Resources\CampaignLeadResource;
use App\Http\Resources\CampaignResource;
use App\Http\Resources\MailboxResource;
use App\Http\Resources\TargetProfileResource;
use App\Http\Resources\TargetProfileSummaryResource;
use App\Models\AgentRun;
use App\Models\Campaign;
use App\Models\CampaignLead;
use App\Models\EmailAccount;
use App\Models\TargetProfile;
use App\Support\AggregateDate;
use App\Support\CurrentProject;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The sequences this project sends, and the editor for one of them.
 *
 * Ids are looked up here rather than type-hinted into the action: route model
 * binding resolves in the `web` group, before the middleware that sets the
 * current project, so a bound model would be fetched while the scope is still
 * inert and any id in the table would resolve.
 */
class CampaignController extends Controller
{
    public function __construct(
        private CurrentProject $currentProject,
        private Settings $settings,
    ) {}

    public function index(WriteMissingCampaigns $missing): Response
    {
        $writing = AgentRun::query()->latestFor(SequenceWriter::slug())->first();

        return Inertia::render('campaigns/Index', [
            'campaigns' => CampaignResource::collection(
                Campaign::query()
                    ->with('targetProfile')
                    ->withCount(['steps', 'campaignLeads as live_leads_count' => fn ($leads) => $leads
                        ->whereIn('status', CampaignLeadStatus::live())])
                    // The list is where the switch is thrown, so it has to say
                    // what the switch did: a campaign nobody is in reads exactly
                    // like one that started fine.
                    ->withMin(['campaignLeads as next_action_at' => fn ($leads) => $leads
                        ->whereIn('status', CampaignLeadStatus::live())], 'next_action_at')
                    ->latest('id')
                    ->get()
            ),
            // Writing three mails on the expensive model takes a minute or two,
            // so the list has to be able to say that something is on its way
            // rather than look like the button did nothing.
            'writing' => $writing?->isInFlight() ?? false,
            'writingError' => $writing?->status === AgentRunStatus::Failed ? $writing->error : null,
            'profiles' => TargetProfileResource::collection(
                TargetProfile::query()->where('is_active', true)->orderBy('id')->get()
            ),
            // What is missing never appears on a list of what exists: a segment
            // with no sequence is one the searches keep filling with companies
            // nobody will ever be written to.
            'uncovered' => TargetProfileSummaryResource::collection($missing->missing()),
        ]);
    }

    /**
     * An empty draft, for somebody who would rather write the whole thing
     * themselves. The generated route is the main path; this is the escape
     * hatch, and it exists so the editor is never unreachable without a model.
     */
    public function store(CampaignRequest $request): RedirectResponse
    {
        $campaign = Campaign::create([
            'project_id' => $this->currentProject->getOrFail()->id,
            ...$request->validated(),
        ]);

        return to_route('campaigns.show', $campaign);
    }

    /**
     * The mails themselves. What is happening to the people in the sequence is
     * a page of its own: they are read at different moments, and one screen
     * carrying both meant scrolling past the run to reach the editor.
     *
     * The preview is an optional prop: personalising a mail is a model call per
     * lead, so it runs only when the page asks for it by name, never on an
     * ordinary visit or a refresh.
     */
    public function show(Request $request, PreviewSequence $preview, int $campaign): Response
    {
        $campaign = Campaign::query()
            ->with(['targetProfile', 'steps.variants'])
            ->findOrFail($campaign);

        return Inertia::render('campaigns/Show', [
            'campaign' => CampaignResource::make($campaign),
            'sample' => Inertia::optional(fn () => $this->currentProject->run(
                $campaign->project,
                fn () => $preview->handle($campaign, $request->integer('preview_step')),
            )),
        ]);
    }

    /**
     * Who is in the sequence, where they have got to, and when the next mail
     * actually leaves.
     */
    public function delivery(DispatchDueSends $dispatcher, int $campaign): Response
    {
        $campaign = Campaign::query()->with('targetProfile')->findOrFail($campaign);

        return Inertia::render('campaigns/Delivery', [
            'campaign' => CampaignResource::make($campaign),
            // Where the people in THIS sequence have got to. The dashboard shows
            // the project's whole funnel; this is the one campaign's, which is
            // what somebody looking at a sequence wants to know.
            'pipeline' => $campaign->campaignLeads()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            // When the next mail is owed, and what is standing in its way. An
            // active campaign that has sent nothing for an hour is the normal
            // case, not a bug, and the screen has to be able to say so.
            'sending' => $this->sendingState($campaign, $dispatcher),
            'leads' => CampaignLeadResource::collection($this->leads($campaign)),
            'leadsTotal' => $campaign->campaignLeads()->count(),
        ]);
    }

    /**
     * How many rows the sequence shows before it stops. Enough to read a run at
     * a glance; the whole list belongs on Contacts, which is built for it.
     */
    private const LEADS_SHOWN = 50;

    /**
     * The people in this sequence, the ones with something owed first, then the
     * rest by how recently anything moved.
     *
     * @return Collection<int, CampaignLead>
     */
    private function leads(Campaign $campaign): Collection
    {
        return $campaign->campaignLeads()
            ->with(['lead.company'])
            ->withCount('sentMessages')
            ->orderByRaw('next_action_at is null')
            ->orderBy('next_action_at')
            ->orderByDesc('id')
            ->limit(self::LEADS_SHOWN)
            ->get();
    }

    /**
     * Everything the answer to "when does the next one go out" is made of.
     *
     * The rules themselves are the scheduler's: the window comes from the
     * action that enforces it, and the allowance and the gap from the mailbox.
     * Restating any of them here is how a screen ends up promising a send the
     * scheduler will not make.
     *
     * @return array<string, mixed>
     */
    private function sendingState(Campaign $campaign, DispatchDueSends $dispatcher): array
    {
        $mailboxes = EmailAccount::query()
            ->whereIn('id', $campaign->campaignLeads()
                ->whereIn('status', CampaignLeadStatus::live())
                ->select('email_account_id'))
            ->get();

        return [
            // Parsed rather than passed through: an aggregate comes back as a
            // raw database string, and every other date on the page is a cast
            // attribute. Two formats reach the same date formatter otherwise.
            'next_action_at' => AggregateDate::parse($campaign->campaignLeads()
                ->whereIn('status', CampaignLeadStatus::live())
                ->min('next_action_at')),
            'window_open' => $dispatcher->windowIsOpen(),
            'window' => [
                'start' => (int) $this->settings->array('sending')['window_start'],
                'end' => (int) $this->settings->array('sending')['window_end'],
            ],
            'mailboxes' => MailboxResource::collection($mailboxes),
        ];
    }

    public function update(CampaignRequest $request, int $campaign): RedirectResponse
    {
        Campaign::query()->findOrFail($campaign)->update($request->validated());

        return back();
    }

    public function destroy(int $campaign): RedirectResponse
    {
        Campaign::query()->findOrFail($campaign)->delete();

        return to_route('campaigns.index');
    }
}
