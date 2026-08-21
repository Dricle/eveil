<?php

namespace App\Http\Controllers;

use App\Ai\Agents\TargetProfileDeriver;
use App\Http\Resources\ProjectAnalysisResource;
use App\Http\Resources\TargetProfileResource;
use App\Models\AgentRun;
use App\Models\DiscoveryRun;
use App\Models\TargetProfile;
use App\Support\CurrentProject;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The first ten minutes, which decide whether anybody comes back.
 *
 * Somebody who has just typed the address of their product should watch it being
 * read, agree with what was understood, and see the search start: not land on a
 * dashboard of zeroes and be left to find four screens in the right order.
 *
 * Each stage is confirmed rather than assumed. The portrait and the segments are
 * what every mail is written from, so a wrong one is worth catching here, before
 * it has been used to write to anybody. What confirmation does is start the next
 * stage: agreeing is the only button, and the queue does the rest.
 */
class OnboardingController extends Controller
{
    public function __construct(private CurrentProject $currentProject) {}

    public function show(): Response
    {
        $project = $this->currentProject->getOrFail()->load('latestAnalysis');
        $profiles = TargetProfile::query()->orderBy('id')->get();

        return Inertia::render('Onboarding', [
            'analysis' => $project->latestAnalysis === null
                ? null
                : ProjectAnalysisResource::make($project->latestAnalysis),
            'knowledgeBase' => $project->knowledge_base,
            // Asked here rather than left for the settings screen: the answers
            // feed the segments derived on the next click, and this is the one
            // moment the user is already watching.
            'openQuestions' => $project->openQuestions(),
            'profiles' => TargetProfileResource::collection($profiles),
            // The same in-flight rule the Targets section uses: a run older than
            // fifteen minutes is not believed, so a worker that never came back
            // cannot spin this screen for ever.
            'deriving' => AgentRun::query()
                ->latestFor(TargetProfileDeriver::slug())
                ->first()?->isInFlight() ?? false,
            'searches' => DiscoveryRun::query()->count(),
        ]);
    }
}
