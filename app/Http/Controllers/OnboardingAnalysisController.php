<?php

namespace App\Http\Controllers;

use App\Enums\AnalysisStatus;
use App\Jobs\AnalyzeProject;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;

/**
 * "Try again" on a site that could not be read. Most failures are not the site
 * at all but a missing provider key or a worker that fell over, and once that
 * is fixed the run should carry on from here rather than from a settings page.
 *
 * Only after a failure: a read still running would otherwise be doubled by an
 * impatient second click, and a successful one is redone from Settings.
 */
class OnboardingAnalysisController extends Controller
{
    public function __construct(private CurrentProject $currentProject) {}

    public function store(): RedirectResponse
    {
        $project = $this->currentProject->getOrFail()->load('latestAnalysis');

        if ($project->latestAnalysis?->status === AnalysisStatus::Failed) {
            AnalyzeProject::dispatch($project);
        }

        return back();
    }
}
