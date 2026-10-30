<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

/**
 * One image of a project's visual identity, or one voice line or music track
 * made for a video, for the editor's preview, which runs sandboxed and
 * carries no session. The signature on the link is the access check: only
 * the preview of a post in that project hands one out
 * (`SocialPostVideoPreviewController`), and it expires.
 */
class VideoAssetController extends Controller
{
    public function show(Request $request, int $media): Response
    {
        return Media::query()->whereIn('collection_name', ['brand_assets', 'video_audio'])->findOrFail($media)->toInlineResponse($request);
    }
}
