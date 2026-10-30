<?php

namespace App\Http\Controllers;

use App\Enums\SocialPostStatus;
use App\Http\Requests\SocialPostVideoRequest;
use App\Http\Resources\SocialPostResource;
use App\Jobs\GenerateSocialPostVideo;
use App\Models\SocialPost;
use App\Support\SocialPostProgress;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The video editor of a draft: the video playing beside the back and forth
 * with the director, and the box to say what should change, at the second
 * the user paused at.
 */
class SocialPostVideoController extends Controller
{
    public function show(int $socialPost): Response
    {
        $post = SocialPost::query()->with(['media', 'project'])->findOrFail($socialPost);

        return Inertia::render('social/VideoEditor', [
            'post' => new SocialPostResource($post),
        ]);
    }

    /**
     * Queues the first video, or a new version from the user's feedback. The
     * editor rereads the post until it shows.
     */
    public function store(SocialPostVideoRequest $request, SocialPostProgress $progress, int $socialPost): RedirectResponse
    {
        $post = SocialPost::query()->where('status', SocialPostStatus::Draft)->findOrFail($socialPost);

        if ($progress->isDirecting($post)) {
            return back()->withErrors(['feedback' => 'A version is already being made: wait for it, then say what to change.']);
        }

        $feedback = trim((string) $request->validated('feedback')) ?: null;
        $at = $request->validated('at');

        $progress->startDirecting($post);

        GenerateSocialPostVideo::dispatch($post, $feedback, $at === null ? null : (float) $at);

        return back();
    }
}
