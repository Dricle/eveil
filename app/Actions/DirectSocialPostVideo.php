<?php

namespace App\Actions;

use App\Ai\Agents\VideoDirector;
use App\Enums\BrandAssetKind;
use App\Models\Project;
use App\Models\SocialPost;
use App\Services\Discovery\PageFetcher;
use App\Services\Video\BrandStyle;
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

    /**
     * Style examples shown to the director beside the logo, newest first.
     */
    private const MAX_EXAMPLES = 3;

    public function __construct(
        private VideoRenderer $renderer,
        private VideoAudio $audio,
        private BrandStyle $style,
        private PageFetcher $fetcher,
    ) {}

    /**
     * @throws VideoRenderFailed when neither attempt built
     */
    public function handle(SocialPost $post, ?string $feedback = null, ?float $at = null): void
    {
        $project = $post->project;

        $revising = $feedback !== null && $post->video_composition !== null;
        $assets = $project->videoAssets(self::MAX_SCREENSHOTS, $revising ? $post->video_composition : null);

        /** @var Collection<string, Media> $screenshots */
        $screenshots = collect($assets)->except('logo');

        $stillTimes = $revising ? $this->stillTimes($at) : [];
        $stills = $revising ? $this->stills($post, $assets, $stillTimes) : [];

        $examples = $project->brandAssets(BrandAssetKind::Example)->sortByDesc('id')->take(self::MAX_EXAMPLES)->values();
        $stored = fn (Media $media): Image => Image::fromStorage($media->getPathRelativeToRoot(), $media->disk);

        // In the order the director is told: screenshots, logo, style examples, stills.
        $images = [
            ...$screenshots->map($stored)->values()->all(),
            ...(isset($assets['logo']) ? [$stored($assets['logo'])] : []),
            ...$examples->map($stored)->all(),
            ...array_map(fn (string $png): Image => Image::fromBase64(base64_encode($png), 'image/png'), $stills),
        ];

        $brand = $this->brand($project, $assets['logo'] ?? null);

        $current = $revising ? $post->video_composition : null;
        $error = null;
        $voiced = $this->audio->available();
        // The current version's audio: what a failed revision must leave alone, and alone.
        $kept = $post->getMedia('video_audio')->pluck('id');

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
                $brand,
                $examples->count(),
            ))->direct()->structured;

            $composition = trim((string) ($structured['composition'] ?? ''));

            try {
                $audio = $voiced ? $this->audioFor($post, $composition, $assets) : [];
                $file = $this->renderer->render($composition, $post->platform->videoFormat(), $assets, $audio);
            } catch (VideoRenderFailed $e) {
                if ($attempt === self::ATTEMPTS) {
                    $post->media()->where('collection_name', 'video_audio')->whereNotIn('id', $kept)->get()->each->delete();

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

            $this->dropUnusedAudio($post, $audio);

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
        $manifest = $this->renderer->manifest($composition, $post->platform->videoFormat(), $assets);
        // A script that names no narrator, or one that does not exist: the
        // voice it already had, so its lines are not paid for again, else any.
        $narrator = isset(VideoAudio::NARRATORS[$manifest['narrator'] ?? ''])
            ? (string) $manifest['narrator']
            : (string) ($kept->first(fn (Media $media): bool => $media->hasCustomProperty('narrator'))?->getCustomProperty('narrator') ?? array_rand(VideoAudio::NARRATORS));

        try {
            foreach ($manifest['voices'] as $text) {
                $audio['voices'][$text] = $kept->first(fn (Media $media): bool => $media->getCustomProperty('voice') === $text && $media->getCustomProperty('narrator') === $narrator)
                    ?? $this->speak($post, $text, $narrator);
            }

            $manifest = $this->renderer->manifest($composition, $post->platform->videoFormat(), $assets, $audio['voices']);

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

        return $audio;
    }

    /**
     * Once the new version is saved, and only then: a version that failed
     * leaves the current one its voice and music.
     *
     * @param  array{voices?: array<string, Media>, music?: Media|null}  $audio
     */
    private function dropUnusedAudio(SocialPost $post, array $audio): void
    {
        $used = collect($audio['voices'] ?? [])->push($audio['music'] ?? null)->filter()->map->id;

        $post->media()->where('collection_name', 'video_audio')->whereNotIn('id', $used)->get()->each->delete();
    }

    /**
     * One voice line, kept on the post with how long it reads: ElevenLabs
     * answers in MP3 at a constant 128 kbit/s, 16 000 bytes a second.
     */
    private function speak(SocialPost $post, string $text, string $narrator): Media
    {
        $mp3 = $this->audio->speak($text, $narrator);

        return $post->addMediaFromString($mp3)
            ->usingFileName(Str::uuid().'.mp3')
            ->withCustomProperties(['voice' => $text, 'narrator' => $narrator, 'seconds' => round(strlen($mp3) / 16000, 2)])
            ->toMediaCollection('video_audio');
    }

    /**
     * What the brand looks like, as hints beside the images: the logo's
     * colours, and the colours and fonts its homepage declares. Best effort:
     * a homepage that cannot be read leaves the logo and examples to speak.
     *
     * @return array{logo_colors: array<int, string>, site_colors: array<int, string>, site_fonts: array<int, string>}
     */
    private function brand(Project $project, ?Media $logo): array
    {
        $site = ['colors' => [], 'fonts' => []];

        try {
            $page = $this->fetcher->fetch((string) $project->url);
            $site = $page?->content ? $this->style->site($page->content) : $site;
        } catch (Throwable) {
            // The homepage is a hint, never a reason not to make the video.
        }

        return [
            'logo_colors' => $logo !== null ? $this->style->palette((string) stream_get_contents($logo->stream())) : [],
            'site_colors' => $site['colors'],
            'site_fonts' => $site['fonts'],
        ];
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
     * the user's words. Staged with the current voice lines, so its scenes
     * are as long as in the video the user paused.
     *
     * @param  array<string, Media>  $assets
     * @param  array<int, float>  $times
     * @return array<int, string>
     */
    private function stills(SocialPost $post, array $assets, array $times): array
    {
        $voices = $post->getMedia('video_audio')
            ->filter(fn (Media $media): bool => $media->hasCustomProperty('voice'))
            ->keyBy(fn (Media $media): string => (string) $media->getCustomProperty('voice'))
            ->all();

        try {
            return $this->renderer->stills((string) $post->video_composition, $post->platform->videoFormat(), $assets, $times, $voices);
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
