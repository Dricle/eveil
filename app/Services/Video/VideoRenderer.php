<?php

namespace App\Services\Video;

use App\Enums\ImageFormat;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Turns a composition (a script for `resources/video/runtime.js`) into an
 * MP4, or into a few still frames for a model to look at.
 *
 * The page is assembled from `resources/video/page.html` in a fresh
 * directory with a local copy of every asset, since the renderer refuses
 * anything that is not a local file: the composition was written by a
 * model, and must not reach the network or the app.
 */
class VideoRenderer
{
    public const FPS = 30;

    /**
     * The page the editor's preview loads and the renderer films: the
     * runtime and its fonts from `$base`, each asset from its URL, and the
     * audio made for this video, each voice line by its text.
     *
     * @param  array<string, string>  $assetUrls  asset name => URL
     * @param  array{voices?: array<string, string>, durations?: array<string, float>, music?: string|null}  $audioUrls
     */
    public function page(string $composition, ImageFormat $format, array $assetUrls, string $base, array $audioUrls = []): string
    {
        [$width, $height] = $format->videoSize();

        $options = json_encode([
            'width' => $width,
            'height' => $height,
            'fps' => self::FPS,
            'assets' => (object) $assetUrls,
            'audio' => [
                'voices' => (object) ($audioUrls['voices'] ?? []),
                // How long each line reads, so its scene can make room for it.
                'durations' => (object) ($audioUrls['durations'] ?? []),
                'music' => $audioUrls['music'] ?? null,
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);

        return strtr(File::get(resource_path('video/page.html')), [
            '{{BASE}}' => e($base),
            '{{OPTIONS}}' => $options,
            // A closing script tag in the composition would end the page's script early.
            '{{COMPOSITION}}' => str_ireplace('</script', '<\/script', $composition),
        ]);
    }

    /**
     * What the composition asks for without filming it: its length, its
     * voice lines and its music, so their audio can be made first. Given the
     * voices already made, the length is the final one, scenes lengthened
     * for their lines.
     *
     * @param  array<string, Media>  $assets
     * @param  array<string, Media>  $voices  voice line => audio
     * @return array{duration: float, voices: array<int, string>, narrator: string|null, music: string|null}
     *
     * @throws VideoRenderFailed
     */
    public function manifest(string $composition, ImageFormat $format, array $assets, array $voices = []): array
    {
        $directory = $this->stage($composition, $format, $assets, ['voices' => $voices]);

        try {
            $manifest = json_decode($this->run($directory, ['-', '--manifest']), true);
        } finally {
            File::deleteDirectory($directory);
        }

        return [
            'duration' => (float) ($manifest['duration'] ?? 0),
            'voices' => array_values(array_unique(array_map('strval', $manifest['voices'] ?? []))),
            'narrator' => isset($manifest['narrator']) ? (string) $manifest['narrator'] : null,
            'music' => isset($manifest['music']) ? (string) $manifest['music'] : null,
        ];
    }

    /**
     * Renders the whole video. The caller owns the returned file and deletes
     * it once stored.
     *
     * @param  array<string, Media>  $assets  asset name => image
     * @param  array{voices?: array<string, Media>, music?: Media|null}  $audio  the audio made for it, each voice line by its text
     *
     * @throws VideoRenderFailed when the composition does not build or the encoder fails
     */
    public function render(string $composition, ImageFormat $format, array $assets, array $audio = []): string
    {
        $directory = $this->stage($composition, $format, $assets, $audio);
        $out = sys_get_temp_dir().'/'.Str::uuid().'.mp4';

        try {
            $this->run($directory, [$out]);
        } finally {
            File::deleteDirectory($directory);
        }

        return $out;
    }

    /**
     * A PNG of the video at each of `$times` (seconds), for a model to see
     * what the user is talking about.
     *
     * @param  array<string, Media>  $assets
     * @param  array<int, float>  $times
     * @param  array<string, Media>  $voices  voice line => audio, so the scenes are as long as in the video
     * @return array<int, string> PNG bytes, in the order of `$times`
     *
     * @throws VideoRenderFailed
     */
    public function stills(string $composition, ImageFormat $format, array $assets, array $times, array $voices = []): array
    {
        $directory = $this->stage($composition, $format, $assets, ['voices' => $voices]);

        try {
            $this->run($directory, ["{$directory}/stills", '--stills='.implode(',', array_map(fn (float $t): string => number_format($t, 2, '.', ''), $times))]);

            return array_map(fn (float $t): string => File::get(sprintf('%s/stills/%.2f.png', $directory, $t)), $times);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    /**
     * A fresh directory holding the page and a copy of every asset and every
     * piece of audio.
     *
     * @param  array<string, Media>  $assets
     * @param  array{voices?: array<string, Media>, music?: Media|null}  $audio
     */
    private function stage(string $composition, ImageFormat $format, array $assets, array $audio = []): string
    {
        $directory = sys_get_temp_dir().'/video-'.Str::uuid();
        File::ensureDirectoryExists($directory.'/assets');

        $copy = function (Media $media, string $name) use ($directory): string {
            $file = $directory.'/assets/'.$name.'.'.(pathinfo($media->file_name, PATHINFO_EXTENSION) ?: 'bin');
            File::put($file, (string) stream_get_contents($media->stream()));

            return 'file://'.$file;
        };

        $urls = [];

        foreach ($assets as $name => $media) {
            $urls[$name] = $copy($media, Str::slug($name));
        }

        $audioUrls = ['voices' => [], 'durations' => [], 'music' => isset($audio['music']) ? $copy($audio['music'], 'music') : null];

        foreach ($audio['voices'] ?? [] as $text => $media) {
            $audioUrls['voices'][$text] = $copy($media, 'voice-'.count($audioUrls['voices']));
            $audioUrls['durations'][$text] = (float) $media->getCustomProperty('seconds', 0);
        }

        File::put($directory.'/page.html', $this->page($composition, $format, $urls, 'file://'.resource_path('video').'/', $audioUrls));

        return $directory;
    }

    /**
     * @param  array<int, string>  $arguments  after the page: the output, and any flag
     * @return string what the renderer printed: the manifest, as JSON
     *
     * @throws VideoRenderFailed
     */
    private function run(string $directory, array $arguments): string
    {
        $result = Process::timeout(config('eveil.video.timeout'))
            ->env([
                'CHROME_BINARY' => config('eveil.video.chrome'),
                'FFMPEG_BINARY' => config('eveil.video.ffmpeg'),
                'VIDEO_TIMEOUT_MS' => (string) (config('eveil.video.timeout') * 1000),
            ])
            ->run([config('eveil.video.node'), resource_path('video/render.mjs'), "{$directory}/page.html", ...$arguments]);

        if ($result->failed()) {
            throw new VideoRenderFailed(trim($result->errorOutput()) ?: 'The renderer stopped without saying why.');
        }

        return $result->output();
    }
}
