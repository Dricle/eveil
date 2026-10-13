<?php

namespace App\Http\Controllers\Api;

use App\Actions\DeleteAllLeads;
use App\Http\Controllers\Controller;
use App\Support\CurrentProject;
use Illuminate\Http\JsonResponse;

/**
 * Every person found or added goes, as from the app's danger zone. Not an
 * erasure: discovery may find the same people again, which is the point of
 * starting over.
 */
class ContactBulkDeleteController extends Controller
{
    public function destroy(CurrentProject $currentProject, DeleteAllLeads $deleteAll): JsonResponse
    {
        return response()->json(['deleted' => $deleteAll->handle($currentProject->getOrFail())]);
    }
}
