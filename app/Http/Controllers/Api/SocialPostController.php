<?php

namespace App\Http\Controllers\Api;

use App\Enums\SocialPlatform;
use App\Enums\SocialPostStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SocialPostStoreRequest;
use App\Http\Requests\SocialPostRequest;
use App\Http\Resources\SocialPostResource;
use App\Jobs\GenerateSocialPost;
use App\Models\SocialPost;
use App\Support\CurrentProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The posts in each network's queue, newest first, and their drafts' text.
 *
 * Queues one post, like the app's "Write a post" button. With a `brief` the
 * post is about that and waits for approval, the same as asking Evie for
 * one. The draft lands in the network's queue in the app; this answers
 * before it is written.
 */
class SocialPostController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return SocialPostResource::collection(
            SocialPost::query()
                ->with('socialAccount')
                ->when($request->string('platform')->value(), fn ($query, string $platform) => $query->where('platform', $platform))
                ->latest('id')
                ->paginate(50)
        );
    }

    /**
     * A draft's text, as edited in the network's queue. Once approved,
     * published or rejected, a post no longer changes: 409.
     */
    public function update(SocialPostRequest $request, int $socialPost): JsonResponse|SocialPostResource
    {
        $post = SocialPost::query()->findOrFail($socialPost);

        if ($post->status !== SocialPostStatus::Draft) {
            return response()->json(['message' => "That post is already {$post->status->value}, so it can no longer be edited."], 409);
        }

        $post->update($request->validated());

        return SocialPostResource::make($post->load('socialAccount'));
    }

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
