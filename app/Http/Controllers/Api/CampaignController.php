<?php

namespace App\Http\Controllers\Api;

use App\Actions\StoreSequence;
use App\Actions\UpdateSequence;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SequenceRequest;
use App\Http\Resources\CampaignResource;
use App\Models\Campaign;
use App\Models\TargetProfile;
use App\Support\CurrentProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * The sequences, with the same counts the app's list shows. The people in
 * one are `CampaignLeadController`, paginated on their own.
 *
 * Written whole, steps and all, through the same actions Evie uses
 * (`StoreSequence`, `UpdateSequence`): a new one is a draft, and only a draft
 * can be rewritten. Activating it stays in the app, where the mailbox and the
 * people it will reach are in front of you.
 */
class CampaignController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CampaignResource::collection(
            Campaign::query()
                ->with('targetProfile')
                ->withDeliveryCounts()
                ->orderBy('id')
                ->paginate(50)
        );
    }

    public function store(SequenceRequest $request, CurrentProject $currentProject, StoreSequence $storeSequence): JsonResponse
    {
        $campaign = $storeSequence->handle(
            $currentProject->getOrFail(),
            TargetProfile::query()->findOrFail($request->integer('target_profile_id')),
            $request->string('name')->value(),
            $request->validated('steps'),
        );

        return $this->show($campaign->id)->response()->setStatusCode(201);
    }

    public function update(SequenceRequest $request, UpdateSequence $updateSequence, int $campaign): JsonResponse|CampaignResource
    {
        try {
            $updateSequence->handle(Campaign::query()->findOrFail($campaign), $request->validated('steps'), $request->validated('name'));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return $this->show($campaign);
    }

    public function show(int $campaign): CampaignResource
    {
        return CampaignResource::make(
            Campaign::query()
                ->with(['targetProfile', 'steps.variants'])
                ->withDeliveryCounts()
                ->findOrFail($campaign)
        );
    }

    /**
     * Deletes the campaign and, with it, where everyone in it stood. The mails
     * already sent stay in the inbox.
     */
    public function destroy(int $campaign): Response
    {
        Campaign::query()->findOrFail($campaign)->delete();

        return response()->noContent();
    }
}
