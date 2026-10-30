<?php

namespace App\Http\Controllers;

use App\Models\SocialPost;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A draft's rendered video, reached through its post like its images
 * (`SocialPostImageController`): the project-scoped lookup is the access
 * check, and the disk may be private.
 */
class SocialPostVideoFileController extends Controller
{
    public function show(Request $request, int $socialPost): Response
    {
        $video = SocialPost::query()->findOrFail($socialPost)->video;

        abort_if($video === null, 404);

        return $video->toInlineResponse($request);
    }
}
