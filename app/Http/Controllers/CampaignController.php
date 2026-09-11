<?php

namespace App\Http\Controllers;

use App\Actions\DescribeCampaignSending;
use App\Actions\DispatchDueSends;
use App\Actions\PreviewSequence;
use App\Actions\WriteMissingCampaigns;
use App\Ai\Agents\SequenceWriter;
use App\Ai\Agents\VariantWriter;
use App\Enums\AgentRunStatus;
use App\Enums\CampaignLeadStatus;
use App\Http\Requests\CampaignRequest;
use App\Http\Resources\CampaignLeadResource;
use App\Http\Resources\CampaignResource;
use App\Http\Resources\TargetProfileResource;
use App\Http\Resources\TargetProfileSummaryResource;
use App\Models\AgentRun;
use App\Models\Campaign;
use App\Models\TargetProfile;
use App\Support\CurrentProject;
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

        // Which run last asked the agent for a second wording, if any is
        // still coming: the same "is it still writing" a screen asks about
        // sequence generation, one level down.
        $writingVariant = AgentRun::query()->latestFor(VariantWriter::slug())->first();

        return Inertia::render('campaigns/Show', [
            'campaign' => CampaignResource::make($campaign),
            'sample' => Inertia::optional(fn () => $this->currentProject->run(
                $campaign->project,
                fn () => $preview->handle($campaign, $request->integer('preview_step')),
            )),
            'writingVariant' => $writingVariant?->isInFlight() ?? false,
            'writingVariantError' => $writingVariant?->status === AgentRunStatus::Failed ? $writingVariant->error : null,
        ]);
    }

    /**
     * Who is in the sequence, where they have got to, and when the next mail
     * actually leaves.
     */
    public function delivery(DispatchDueSends $dispatcher, DescribeCampaignSending $sending, int $campaign): Response
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
            'sending' => $sending->handle($campaign, $dispatcher),
            // The ones with something owed first, then the rest by how recently
            // anything moved. Capped at 50: enough to read a run at a glance,
            // and the whole list belongs on Contacts, which is built for it.
            'leads' => CampaignLeadResource::collection($campaign->campaignLeads()
                ->with(['lead.company'])
                ->withCount('sentMessages')
                ->orderedForDeliveryScreen()
                ->limit(50)
                ->get()),
            'leadsTotal' => $campaign->campaignLeads()->count(),
        ]);
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
