<?php

namespace App\Http\Controllers\Api;

use App\Actions\UpdateInsight;
use App\Enums\InsightKind;
use App\Enums\InsightStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\InsightRequest;
use App\Http\Resources\InsightResource;
use App\Models\Insight;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The Insights feed: open items, newest first (`?kind=competitor_mention`,
 * `?unread=1`). Marking one read, done or dismissed is the same
 * `PATCH` the app sends.
 */
class InsightController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return InsightResource::collection(Insight::query()
            ->where('status', InsightStatus::Open)
            ->when(InsightKind::tryFrom($request->string('kind')->value()), fn ($query, InsightKind $kind) => $query->where('kind', $kind))
            ->when($request->boolean('unread'), fn ($query) => $query->whereNull('read_at'))
            ->latest('id')
            ->paginate(50));
    }

    public function update(InsightRequest $request, UpdateInsight $updateInsight, int $insight): JsonResponse
    {
        $idea = Insight::query()->where('status', InsightStatus::Open)->findOrFail($insight);

        $updateInsight->handle(
            $idea,
            $request->has('read') ? $request->boolean('read') : null,
            InsightStatus::tryFrom($request->string('status')->value()),
        );

        return response()->json(new InsightResource($idea));
    }
}
