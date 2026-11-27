<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LaunchSubmissionRequest;
use App\Http\Resources\LaunchItemResource;
use App\Models\LaunchItem;

/**
 * Records a post made on a launch venue, by its link.
 */
class LaunchSubmissionController extends Controller
{
    public function store(LaunchSubmissionRequest $request, int $launchVenue): LaunchItemResource
    {
        $item = LaunchItem::query()->findOrFail($launchVenue);
        $item->submissions()->create(['url' => $request->validated('url'), 'posted_at' => now()]);

        return LaunchItemResource::make($item->load(['venue', 'submissions', 'latestSubmission', 'agentRun']));
    }
}
