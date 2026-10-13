<?php

namespace App\Http\Controllers\Api;

use App\Actions\DeleteAllCompanies;
use App\Http\Controllers\Controller;
use App\Support\CurrentProject;
use Illuminate\Http\JsonResponse;

/**
 * Start the project's discovery over: every company goes, as from the app's
 * danger zone. The people found there stay, unattached (`DELETE /contacts`
 * is the other half).
 */
class CompanyBulkDeleteController extends Controller
{
    public function destroy(CurrentProject $currentProject, DeleteAllCompanies $deleteAll): JsonResponse
    {
        return response()->json(['deleted' => $deleteAll->handle($currentProject->getOrFail())]);
    }
}
