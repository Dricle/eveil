<?php

namespace App\Http\Controllers\Api;

use App\Enums\InsightKind;
use App\Enums\InsightStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\InsightResource;
use App\Models\Insight;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * The open article topics on the SEO page, the most asked first. Write one with
 * `POST /articles {"idea_id": ...}`; dismissing one here takes it off the
 * list for good, as in the app.
 */
class IdeaController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => InsightResource::collection(
            Insight::query()->where('kind', InsightKind::Article)->where('status', InsightStatus::Open)->orderByDesc('mentions')->latest('id')->get()
        )]);
    }

    public function destroy(int $idea): Response
    {
        Insight::query()->where('kind', InsightKind::Article)->where('status', InsightStatus::Open)->findOrFail($idea)
            ->update(['status' => InsightStatus::Dismissed]);

        return response()->noContent();
    }
}
