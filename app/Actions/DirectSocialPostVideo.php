<?php

namespace App\Actions;

use App\Ai\Agents\VideoDirector;
use App\Models\SocialPost;
use App\Services\Video\VideoAudio;
use App\Services\Video\VideoRenderer;
use App\Services\Video\VideoRenderFailed;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Ai\Files\Image;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Makes, or remakes, the video that goes out with a post: `VideoDirector`
 * writes the script, `VideoRenderer` films it, and the file replaces the
 * previous one in the post's `videos` collection.
 *
 * With `$feedback`, it is a revision of the current video: the director gets
 * its script back, what the user said, and stills of the video around the
 * second they paused at. A script that does not build goes back to the
 * director once with the error; a second failure is the caller's to report.
 *
 * When the instance has an ElevenLabs key, the script may also speak and
 * carry music: those are made between building the script and filming it.
 */
class DirectSocialPostVideo
{
    /**
     * Screenshots shown to the director, newest first: each one is read as
     * an image, which is where most of the call's input goes.
     */
    private const MAX_SCREENSHOTS = 6;

    private const ATTEMPTS = 2;

    public function __construct(private VideoRenderer $renderer, private VideoAudio $audio) {}

    /**
     * @throws VideoRenderFailed when neither attempt built
     */
    public function handle(SocialPost $post, ?string $feedback = null, ?float $at = null): void
    {
        $project = $post->project;

        $assets = $project->videoAssets(self::MAX_SCREENSHOTS);

        /** @var Collection<string, Media> $screenshots */
        $screenshots = collect($assets)->except('logo');

        $revising = $feedback !== null && $post->video_composition !== null;
        $stillTimes = $revising ? $this->stillTimes($at) : [];
        $stills = $revising ? $this->stills($post->video_composition, $assets, $stillTimes) : [];

        $images = [
            ...$screenshots->map(fn (Media $media): Image => Image::fromStorage($media->getPathRelativeToRoot(), $media->disk))->values()->all(),
            ...array_map(fn (string $png): Image => Image::fromBase64(base64_encode($png), 'image/png'), $stills),
        ];

        $current = $revising ? $post->video_composition : null;
        $error = null;
        $voiced = $this->audio->available();

        foreach (range(1, self::ATTEMPTS) as $attempt) {
            $structured = (new VideoDirector(
                $project,
                $post->platform,
                $post->body,
                trim((string) $post->video_brief) ?: $post->body,
                $screenshots->map(fn (Media $media): array => [
                    'description' => (string) ($media->getCustomProperty('description') ?: $media->name),
                    ...$this->size($media),
                ])->all(),
                isset($assets['logo']),
                $this->recentCompositions($post),
                $images,
                $current,
                $feedback,
                $at,
                $stills === [] ? [] : $stillTimes,
                $post->video_revisions ?? [],
                $error,
                $voiced,
            ))->direct()->structured;

            $composition = trim((string) ($structured['composition'] ?? ''));

            try {
                $audio = $voiced ? $this->audioFor($post, $composition, $assets) : [];
                $file = $this->renderer->render($composition, $assets, $audio);
            } catch (VideoRenderFailed $e) {
                if ($attempt === self::ATTEMPTS) {
                    throw $e;
                }

                $current = $composition;
                $error = Str::limit($e->getMessage(), 2000);

                continue;
            }

            $video = $post->addMedia($file)
                ->usingFileName(Str::uuid().'.mp4')
                ->toMediaCollection('videos');

            $post->update([
                'video_composition' => $composition,
                'video_media_id' => $video->id,
                'video_revisions' => [
                    ...($post->video_revisions ?? []),
                    ['feedback' => $feedback, 'at' => $at, 'summary' => (string) ($structured['summary'] ?? ''), 'created_at' => now()->toIso8601String()],
                ],
                'last_error' => null,
            ]);

            return;
        }
    }

    /**
     * The voice lines and the music the script asks for, as files on the
     * post. Each is made once and kept: a new version that keeps a line or
     * the music reuses it rather than paying for it again, and whatever the
     * new script no longer uses is dropped. Best effort: when ElevenLabs
     * fails, the video goes out with its type and sounds alone.
     *
     * The voices come first: each line's length can lengthen its scene, so
     * the music is asked for at the video's final length.
     *
     * @param  array<string, Media>  $assets
     * @return array{voices?: array<string, Media>, music?: Media|null}
     *
     * @throws VideoRenderFailed when the script does not build
     */
    private function audioFor(SocialPost $post, string $composition, array $assets): array
    {
        $kept = $post->getMedia('video_audio');
        $audio = ['voices' => [], 'music' => null];
        $manifest = $this->renderer->manifest($composition, $assets);

        try {
            foreach ($manifest['voices'] as $text) {
                $audio['voices'][$text] = $kept->first(fn (Media $media): bool => $media->getCustomProperty('voice') === $text)
                    ?? $this->speak($post, $text);
            }

            $manifest = $this->renderer->manifest($composition, $assets, $audio['voices']);

            if ($manifest['music'] !== null) {
                $audio['music'] = $kept->first(fn (Media $media): bool => $media->getCustomProperty('music') === $manifest['music'] && $media->getCustomProperty('seconds') >= $manifest['duration'])
                    ?? $post->addMediaFromString($this->audio->compose($manifest['music'], $manifest['duration']))
                        ->usingFileName(Str::uuid().'.mp3')
                        ->withCustomProperties(['music' => $manifest['music'], 'seconds' => $manifest['duration']])
                        ->toMediaCollection('video_audio');
            }
        } catch (Throwable) {
            return [];
        }

        $used = collect($audio['voices'])->push($audio['music'])->filter()->map->id;
        $kept->reject(fn (Media $media): bool => $used->contains($media->id))->each->delete();

        return $audio;
    }

    /**
     * One voice line, kept on the post with how long it reads: ElevenLabs
     * answers in MP3 at a constant 128 kbit/s, 16 000 bytes a second.
     */
    private function speak(SocialPost $post, string $text): Media
    {
        $mp3 = $this->audio->speak($text);

        return $post->addMediaFromString($mp3)
            ->usingFileName(Str::uuid().'.mp3')
            ->withCustomProperties(['voice' => $text, 'seconds' => round(strlen($mp3) / 16000, 2)])
            ->toMediaCollection('video_audio');
    }

    /**
     * Around the second the user paused at, or spread over the video when
     * they said nothing about when.
     *
     * @return array<int, float>
     */
    private function stillTimes(?float $at): array
    {
        if ($at === null) {
            return [1.5, 5.0, 9.0, 14.0];
        }

        return array_values(array_unique([max(0.0, round($at - 0.6, 2)), round($at, 2), round($at + 0.8, 2)]));
    }

    /**
     * Best effort: without stills the director still has its script and
     * the user's words.
     *
     * @param  array<string, Media>  $assets
     * @param  array<int, float>  $times
     * @return array<int, string>
     */
    private function stills(string $composition, array $assets, array $times): array
    {
        try {
            return $this->renderer->stills($composition, $assets, $times);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array{width: int, height: int}
     */
    private function size(Media $media): array
    {
        $size = @getimagesizefromstring((string) stream_get_contents($media->stream()));

        return ['width' => (int) ($size[0] ?? 0), 'height' => (int) ($size[1] ?? 0)];
    }

    /**
     * @return array<int, string>
     */
    private function recentCompositions(SocialPost $post): array
    {
        return SocialPost::query()
            ->where('project_id', $post->project_id)
            ->whereNotNull('video_composition')
            ->whereKeyNot($post->id)
            ->latest()
            ->limit(3)
            ->pluck('video_composition')
            ->all();
    }
}
