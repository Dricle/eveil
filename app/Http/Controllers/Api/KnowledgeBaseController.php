<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\KnowledgeBaseRequest;
use App\Support\CurrentProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

/**
 * What the project knows about the product, the portrait every agent reads.
 * Written with the app's rules, and marked hand-edited the same way, so the
 * next site analysis does not overwrite the correction. Open questions and
 * recommendations live elsewhere in the same column and are left out.
 */
class KnowledgeBaseController extends Controller
{
    public function show(CurrentProject $currentProject): JsonResponse
    {
        return response()->json(Arr::only(
            $currentProject->getOrFail()->knowledge_base ?? [],
            array_keys((new KnowledgeBaseRequest)->rules()),
        ));
    }

    public function update(KnowledgeBaseRequest $request, CurrentProject $currentProject): JsonResponse
    {
        $project = $currentProject->getOrFail();

        $project->update([
            'knowledge_base' => [...$project->knowledge_base ?? [], ...$request->validated()],
            'knowledge_base_edited_by_user' => true,
        ]);

        return response()->json(Arr::only($project->knowledge_base ?? [], array_keys($request->rules())));
    }
}
