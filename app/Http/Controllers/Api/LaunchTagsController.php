<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LaunchTagsRequest;
use App\Models\Project;
use App\Support\CurrentProject;
use Illuminate\Http\JsonResponse;

/**
 * What the product is, for picking its launch venues: the tags in use, and
 * whether the user chose them or they are the website analysis's guess.
 */
class LaunchTagsController extends Controller
{
    public function show(CurrentProject $currentProject): JsonResponse
    {
        return $this->tags($currentProject->getOrFail());
    }

    public function update(LaunchTagsRequest $request, CurrentProject $currentProject): JsonResponse
    {
        $project = $currentProject->getOrFail();
        $project->update(['launch_tags' => array_values(array_unique($request->validated('tags')))]);

        return $this->tags($project);
    }

    private function tags(Project $project): JsonResponse
    {
        return response()->json(['data' => [
            'tags' => $project->launchTags(),
            'chosen_by_user' => $project->launch_tags !== null,
        ]]);
    }
}
