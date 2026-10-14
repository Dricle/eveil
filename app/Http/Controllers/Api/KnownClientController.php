<?php

namespace App\Http\Controllers\Api;

use App\Actions\MarkKnownClients;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\KnownClientStoreRequest;
use App\Support\CurrentProject;
use Illuminate\Http\JsonResponse;

/**
 * "Existing clients" on Companies, for a CRM: its customers, marked so no
 * campaign ever cold-mails them.
 */
class KnownClientController extends Controller
{
    public function store(KnownClientStoreRequest $request, MarkKnownClients $markKnownClients, CurrentProject $currentProject): JsonResponse
    {
        return response()->json($markKnownClients->handle($currentProject->getOrFail(), $request->validated('entries')));
    }
}
