<?php

namespace App\Http\Controllers;

use App\Enums\SocialPostStatus;
use App\Http\Requests\SocialPostImageRequest;
use App\Jobs\GenerateSocialPostImage;
use App\Models\SocialPost;
use App\Support\SocialPostProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The images of a draft on a network that needs one: serve one, ask for
 * another, pick which one goes out.
 *
 * Every image is reached through its post, and the post through the
 * project-scoped lookup, so that lookup is the access check: an image of
 * another project's post is a 404 like the post itself. The `media` table
 * has no project of its own, so a media id is never looked up on its own.
 */
class SocialPostImageController extends Controller
{
    /**
     * Served through the app rather than linked from the disk: the disk may
     * be private, the default local one is.
     */
    public function show(Request $request, int $socialPost, int $image): Response
    {
        return SocialPost::query()->findOrFail($socialPost)->media()->where('collection_name', 'images')->findOrFail($image)->toInlineResponse($request);
    }

    /**
     * Queues one more image; the queue page shows it once drawn. The image
     * already picked stays picked.
     */
    public function store(SocialPostImageRequest $request, SocialPostProgress $progress, int $socialPost): RedirectResponse
    {
        $post = SocialPost::query()->where('status', SocialPostStatus::Draft)->findOrFail($socialPost);

        abort_unless($post->platform->requiresImage(), 404);

        $progress->startDrawing($post);

        GenerateSocialPostImage::dispatch($post, $request->validated('note'));

        return back();
    }

    /**
     * Picks the image the draft will be published with.
     */
    public function update(int $socialPost, int $image): RedirectResponse
    {
        $post = SocialPost::query()->where('status', SocialPostStatus::Draft)->findOrFail($socialPost);

        $post->update(['image_media_id' => $post->media()->where('collection_name', 'images')->findOrFail($image)->id]);

        return back();
    }
}
