<?php

namespace App\Http\Controllers;

use App\Models\AdCreative;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A creative's picture, served through the app rather than from the disk:
 * the media may sit on a private bucket, and only the project sees it.
 */
class AdCreativeImageController extends Controller
{
    public function show(Request $request, int $adCreative): Response
    {
        $image = AdCreative::query()->findOrFail($adCreative)->getFirstMedia('image');

        abort_if($image === null, 404);

        return $image->toInlineResponse($request);
    }
}
