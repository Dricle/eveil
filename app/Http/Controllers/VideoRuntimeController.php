<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The video runtime, its fonts and its sounds, for the editor's preview.
 * Public: the preview runs sandboxed and carries no session, and these files
 * are the same for everyone. The route only matches the files that exist.
 */
class VideoRuntimeController extends Controller
{
    public function show(string $file): BinaryFileResponse
    {
        $path = resource_path("video/{$file}");

        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => match (pathinfo($path, PATHINFO_EXTENSION)) {
                'js' => 'text/javascript',
                'ttf' => 'font/ttf',
                default => 'audio/mpeg',
            },
            'Cache-Control' => 'public, max-age=3600',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}
