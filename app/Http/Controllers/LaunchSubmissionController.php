<?php

namespace App\Http\Controllers;

use App\Http\Requests\LaunchSubmissionRequest;
use App\Models\LaunchItem;
use Illuminate\Http\RedirectResponse;

/**
 * "Posted": records the post's URL against a checklist row. A venue with a
 * cooldown can take several over time, which is why this adds a row rather
 * than setting a flag.
 */
class LaunchSubmissionController extends Controller
{
    public function store(LaunchSubmissionRequest $request, int $launchItem): RedirectResponse
    {
        LaunchItem::query()->findOrFail($launchItem)
            ->submissions()->create(['url' => $request->validated('url'), 'posted_at' => now()]);

        return back();
    }
}
