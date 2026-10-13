<?php

namespace App\Http\Controllers\Api;

use App\Enums\IdeaKind;
use App\Enums\IdeaStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\IdeaResource;
use App\Models\Idea;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * The open article ideas on the SEO page. Write one with
 * `POST /articles {"idea_id": ...}`; dismissing one here takes it off the
 * list for good, as in the app.
 */
class IdeaController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => IdeaResource::collection(
            Idea::query()->where('kind', IdeaKind::Article)->where('status', IdeaStatus::Open)->latest('id')->get()
        )]);
    }

    public function destroy(int $idea): Response
    {
        Idea::query()->where('kind', IdeaKind::Article)->where('status', IdeaStatus::Open)->findOrFail($idea)
            ->update(['status' => IdeaStatus::Dismissed]);

        return response()->noContent();
    }
}
