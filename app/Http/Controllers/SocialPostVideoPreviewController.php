<?php

namespace App\Http\Controllers;

use App\Models\SocialPost;
use App\Services\Video\VideoRenderer;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The live preview of a draft's video: the same page the renderer films,
 * played in the editor's frame.
 *
 * The composition was written by a model, so the page runs sandboxed (the
 * frame has no `allow-same-origin`): it cannot read the app's cookies or
 * reach into the editor. That is also why it loads the runtime and the
 * images from routes that need no session (`VideoRuntimeController`, and
 * signed links to `VideoAssetController`), and why its policy refuses any
 * connection: nothing it runs can send anything anywhere. The policy also
 * sandboxes it itself, so a preview opened in a tab of its own runs as
 * isolated as in the editor's frame, never under the app's origin.
 */
class SocialPostVideoPreviewController extends Controller
{
    public function show(VideoRenderer $renderer, int $socialPost): Response
    {
        $post = SocialPost::query()->with(['project', 'media'])->findOrFail($socialPost);

        abort_if($post->video_composition === null, 404);

        $signed = fn (Media $media): string => URL::temporarySignedRoute('video.assets.show', now()->addHours(2), ['media' => $media->id]);
        $assets = array_map($signed, $post->project->videoAssets(composition: $post->video_composition));

        // The voice lines and the music made for it, by their text and prompt.
        $audio = $post->getMedia('video_audio');
        $audioUrls = [
            'voices' => $audio->filter(fn (Media $media): bool => $media->hasCustomProperty('voice'))
                ->mapWithKeys(fn (Media $media): array => [(string) $media->getCustomProperty('voice') => $signed($media)])
                ->all(),
            'durations' => $audio->filter(fn (Media $media): bool => $media->hasCustomProperty('voice'))
                ->mapWithKeys(fn (Media $media): array => [(string) $media->getCustomProperty('voice') => (float) $media->getCustomProperty('seconds', 0)])
                ->all(),
            'music' => ($music = $audio->first(fn (Media $media): bool => $media->hasCustomProperty('music'))) ? $signed($music) : null,
        ];

        $origin = rtrim(url('/'), '/');

        $base = Str::beforeLast(route('video.runtime.show', ['file' => 'runtime.js']), 'runtime.js');

        return response($renderer->page($post->video_composition, $post->platform->videoFormat(), $assets, $base, $audioUrls))
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('Content-Security-Policy', "default-src 'none'; script-src 'unsafe-inline' {$origin}; style-src 'unsafe-inline'; img-src {$origin} data:; font-src {$origin}; media-src {$origin}; connect-src 'none'; frame-ancestors 'self'; sandbox allow-scripts")
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
