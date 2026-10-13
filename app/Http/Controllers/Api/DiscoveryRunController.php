<?php

namespace App\Http\Controllers\Api;

use App\Actions\RunDiscovery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DiscoveryRunStoreRequest;
use App\Http\Resources\DiscoveryRunResource;
use App\Models\DiscoveryRun;
use App\Models\TargetProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Searches for new companies, one target profile each: start one, then watch
 * it run. Newest first, unlike the other lists: what a caller wants is the
 * run it just started.
 */
class DiscoveryRunController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return DiscoveryRunResource::collection(
            DiscoveryRun::query()
                ->with('targetProfile')
                ->when($request->integer('target_profile_id'), fn ($query, int $profile) => $query->where('target_profile_id', $profile))
                ->latest('id')
                ->paginate(50)
        );
    }

    public function show(int $discoveryRun): DiscoveryRunResource
    {
        return DiscoveryRunResource::make(DiscoveryRun::query()->with('targetProfile')->findOrFail($discoveryRun));
    }

    /**
     * `guidance` steers this one run without changing the profile, like
     * asking Evie for "a search focused on...".
     */
    public function store(DiscoveryRunStoreRequest $request, RunDiscovery $discover): JsonResponse
    {
        $run = $discover->handle(
            TargetProfile::query()->findOrFail($request->integer('target_profile_id')),
            guidance: $request->validated('guidance'),
        );

        return DiscoveryRunResource::make($run->load('targetProfile'))->response()->setStatusCode(201);
    }
}
