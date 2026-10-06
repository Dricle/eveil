<?php

namespace App\Http\Controllers\Api;

use App\Enums\SocialPlatform;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SocialPostStoreRequest;
use App\Jobs\GenerateSocialPost;
use App\Support\CurrentProject;
use Illuminate\Http\JsonResponse;

/**
 * Queues one post, like the app's "Write a post" button. With a `brief` the
 * post is about that and waits for approval, the same as asking Evie for
 * one. The draft lands in the network's queue in the app; this answers
 * before it is written.
 */
class SocialPostController extends Controller
{
    public function store(SocialPostStoreRequest $request, CurrentProject $currentProject): JsonResponse
    {
        GenerateSocialPost::dispatch(
            $currentProject->getOrFail(),
            SocialPlatform::from($request->validated('platform')),
            $request->validated('brief'),
        );

        return response()->json(['message' => 'Queued.'], 202);
    }
}
