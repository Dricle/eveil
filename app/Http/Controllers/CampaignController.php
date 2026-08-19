<?php

namespace App\Http\Controllers;

use App\Actions\EnrolCampaign;
use App\Actions\PreviewSequence;
use App\Ai\Agents\SequenceWriter;
use App\Enums\AgentRunStatus;
use App\Enums\CampaignStatus;
use App\Http\Requests\CampaignRequest;
use App\Http\Resources\CampaignResource;
use App\Http\Resources\TargetProfileResource;
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
    public function __construct(private CurrentProject $currentProject) {}

    public function index(): Response
    {
        $writing = AgentRun::query()->latestFor(SequenceWriter::slug())->first();

        return Inertia::render('campaigns/Index', [
            'campaigns' => CampaignResource::collection(
                Campaign::query()->with('targetProfile')->withCount('steps')->latest('id')->get()
            ),
            // Writing three mails on the expensive model takes a minute or two,
            // so the list has to be able to say that something is on its way
            // rather than look like the button did nothing.
            'writing' => $writing?->isInFlight() ?? false,
            'writingError' => $writing?->status === AgentRunStatus::Failed ? $writing->error : null,
            'profiles' => TargetProfileResource::collection(
                TargetProfile::query()->where('is_active', true)->orderBy('id')->get()
            ),
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
            // Where the people in THIS sequence have got to. The dashboard shows
            // the project's whole funnel; this is the one campaign's, which is
            // what somebody looking at a sequence wants to know.
            'pipeline' => $campaign->campaignLeads()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'sample' => Inertia::optional(fn () => $this->currentProject->run(
                $campaign->project,
                fn () => $preview->handle($campaign, $request->integer('preview_step')),
            )),
        ]);
    }

    public function update(CampaignRequest $request, EnrolCampaign $enrol, int $campaign): RedirectResponse
    {
        $campaign = Campaign::query()->findOrFail($campaign);

        // Read before the write: activating is what puts people into the
        // sequence, and until then a campaign is only a document. Enrolling on
        // every save would re-add everybody suppressed or won since.
        $activating = $campaign->status !== CampaignStatus::Active
            && $request->enum('status', CampaignStatus::class) === CampaignStatus::Active;

        $campaign->update($request->validated());

        if ($activating) {
            $enrol->handle($campaign);
        }

        return back();
    }

    public function destroy(int $campaign): RedirectResponse
    {
        Campaign::query()->findOrFail($campaign)->delete();

        return to_route('campaigns.index');
    }
}
