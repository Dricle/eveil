<?php

namespace App\Http\Controllers;

use App\Http\Requests\LaunchTagsRequest;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;

/**
 * The tags ticked on the Launch page. From then on they are the user's, and
 * the website analysis's guess no longer applies. The list follows on the
 * next read.
 */
class LaunchTagsController extends Controller
{
    public function update(LaunchTagsRequest $request, CurrentProject $currentProject): RedirectResponse
    {
        $currentProject->getOrFail()->update(['launch_tags' => array_values(array_unique($request->validated('tags')))]);

        return back();
    }
}
