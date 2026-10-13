<?php

namespace App\Http\Controllers\Api;

use App\Enums\TargetProfileSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\TargetProfileRequest;
use App\Http\Resources\TargetProfileResource;
use App\Models\TargetProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Who the project is looking for, with the app's own rules: the same request
 * validates both, and a profile written here is the user's from then on
 * (`source: human`), so the next derivation leaves it alone. Not paginated:
 * a project has a handful.
 */
class TargetProfileController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => TargetProfileResource::collection(TargetProfile::query()->orderBy('id')->get())]);
    }

    public function show(int $targetProfile): TargetProfileResource
    {
        return TargetProfileResource::make(TargetProfile::query()->findOrFail($targetProfile));
    }

    public function store(TargetProfileRequest $request): JsonResponse
    {
        $profile = TargetProfile::create([
            ...$request->columns(),
            'criteria' => $request->criteria(),
            'source' => TargetProfileSource::Human,
        ]);

        return TargetProfileResource::make($profile)->response()->setStatusCode(201);
    }

    public function update(TargetProfileRequest $request, int $targetProfile): TargetProfileResource
    {
        $profile = TargetProfile::query()->findOrFail($targetProfile);

        $profile->update([
            ...$request->columns(),
            // Merged, as in the app: `confidence` is the model's report on its
            // own run, not something a correction restates.
            'criteria' => [...$profile->criteria, ...$request->criteria()],
            'source' => TargetProfileSource::Human,
        ]);

        return TargetProfileResource::make($profile);
    }

    public function destroy(int $targetProfile): Response
    {
        TargetProfile::query()->findOrFail($targetProfile)->delete();

        return response()->noContent();
    }
}
