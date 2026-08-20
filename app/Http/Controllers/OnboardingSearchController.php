<?php

namespace App\Http\Controllers;

use App\Actions\RunDiscovery;
use App\Models\TargetProfile;
use Illuminate\Http\RedirectResponse;

/**
 * "These are the right people": the last confirmation of the first run, and the
 * one that starts the searching.
 *
 * One run per active profile, because that is what the user just agreed to: a
 * profile they left switched on is one they want looked for. Each run carries
 * its own budget, so this cannot become an unbounded fan-out.
 */
class OnboardingSearchController extends Controller
{
    public function store(RunDiscovery $discover): RedirectResponse
    {
        TargetProfile::query()
            ->where('is_active', true)
            ->whereDoesntHave('discoveryRuns')
            ->orderBy('id')
            ->get()
            ->each(fn (TargetProfile $profile) => $discover->handle($profile));

        return back();
    }
}
