<?php

namespace App\Http\Controllers\Api;

use App\Enums\RedditReplyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RedditReplyRequest;
use App\Http\Resources\RedditReplyResource;
use App\Models\RedditReply;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The Reddit replies drafted for the project, newest first, and their
 * drafts' text. Posting one stays manual, in the app.
 */
class RedditReplyController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return RedditReplyResource::collection(RedditReply::query()->latest('id')->paginate(50));
    }

    public function show(int $redditReply): RedditReplyResource
    {
        return RedditReplyResource::make(RedditReply::query()->findOrFail($redditReply));
    }

    public function update(RedditReplyRequest $request, int $redditReply): JsonResponse|RedditReplyResource
    {
        $reply = RedditReply::query()->findOrFail($redditReply);

        if ($reply->status !== RedditReplyStatus::Draft) {
            return response()->json(['message' => "That reply is already {$reply->status->value}, so it can no longer be edited."], 409);
        }

        $reply->update($request->validated());

        return RedditReplyResource::make($reply);
    }
}
