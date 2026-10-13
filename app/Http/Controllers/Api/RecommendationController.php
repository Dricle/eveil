<?php

namespace App\Http\Controllers\Api;

use App\Actions\AddRecommendation;
use App\Actions\RemoveRecommendation;
use App\Actions\UpdateRecommendation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RecommendationStoreRequest;
use App\Http\Requests\Api\RecommendationUpdateRequest;
use App\Support\CurrentProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * The acquisition and feature ideas on the project's dashboard. They have a
 * `key`, not an id: it is what every update names them by.
 */
class RecommendationController extends Controller
{
    public function index(CurrentProject $currentProject): JsonResponse
    {
        return response()->json(['data' => $currentProject->getOrFail()->recommendations()]);
    }

    public function store(RecommendationStoreRequest $request, CurrentProject $currentProject, AddRecommendation $add): JsonResponse
    {
        $project = $currentProject->getOrFail();
        $key = $add->handle($project, [
            'idea' => $request->string('idea')->value(),
            'evidence' => $request->string('evidence')->value(),
            'impact' => $request->string('impact')->value(),
            'effort' => $request->string('effort')->value(),
            'kind' => $request->validated('kind'),
        ]);

        return response()->json(collect($project->recommendations())->firstWhere('key', $key), 201);
    }

    public function update(RecommendationUpdateRequest $request, CurrentProject $currentProject, UpdateRecommendation $update, string $recommendation): JsonResponse
    {
        $project = $currentProject->getOrFail();

        abort_unless($update->handle($project, $recommendation, $request->validated()), 404);

        return response()->json(collect($project->recommendations())->firstWhere('key', $recommendation));
    }

    /**
     * Gone for good. To keep it on record as decided, `PATCH` its status to
     * `archived` instead.
     */
    public function destroy(CurrentProject $currentProject, RemoveRecommendation $remove, string $recommendation): Response
    {
        abort_unless($remove->handle($currentProject->getOrFail(), $recommendation), 404);

        return response()->noContent();
    }
}
