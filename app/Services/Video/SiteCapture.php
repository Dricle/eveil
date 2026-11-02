<?php

namespace App\Services\Video;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Throwable;

/**
 * Photographs a product's website with the renderer's Chromium
 * (`resources/video/capture.mjs`): the top of the homepage and further
 * down on a desktop, the top on a phone, and optionally a logo Chromium can
 * draw but GD cannot read (an SVG), as a transparent PNG.
 *
 * Best effort: a site that will not open, or a machine without Chromium,
 * gives nothing back rather than an error.
 */
class SiteCapture
{
    /**
     * @return array{shots: array<int, array{bytes: string, view: string}>, logo: string|null}
     */
    public function capture(string $url, ?string $logoUrl = null): array
    {
        $directory = storage_path('app/private/capture-'.Str::uuid());

        try {
            $result = Process::timeout(180)
                ->env([
                    'CHROME_BINARY' => config('eveil.video.chrome'),
                    'VIDEO_TIMEOUT_MS' => '170000',
                ])
                ->run(array_filter([config('eveil.video.node'), resource_path('video/capture.mjs'), $url, $directory, $logoUrl !== null ? "--logo={$logoUrl}" : null]));

            /** @var array{shots?: array<int, array{file: string, view: string}>, logo?: string|null}|null $written */
            $written = $result->successful() ? json_decode($result->output(), true) : null;

            return [
                'shots' => array_map(fn (array $shot): array => ['bytes' => File::get("{$directory}/{$shot['file']}"), 'view' => $shot['view']], $written['shots'] ?? []),
                'logo' => isset($written['logo']) ? File::get("{$directory}/{$written['logo']}") : null,
            ];
        } catch (Throwable) {
            return ['shots' => [], 'logo' => null];
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
