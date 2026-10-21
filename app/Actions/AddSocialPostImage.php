<?php

namespace App\Actions;

use App\Ai\Agents\ImageGenerator;
use App\Enums\BrandAssetKind;
use App\Enums\SocialPlatform;
use App\Models\Project;
use App\Models\SocialPost;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Draws one more image for a draft and keeps it beside the others. The
 * first one a draft gets is picked straight away; after that, a new image
 * is only a candidate: the user picks, and nothing they liked is replaced
 * behind their back.
 *
 * Drawn from the writer's own brief, or the post itself when there is none,
 * plus whatever the user asked to change, and from the project's own images
 * (`draw()`).
 */
class AddSocialPostImage
{
    /**
     * At most this many style examples and illustrations each per image: with
     * the logo, seven references, within what every image provider takes.
     */
    private const MAX_PER_KIND = 3;

    public function handle(SocialPost $post, ?string $note = null): Media
    {
        $brief = trim($post->image_brief ?? '') ?: $post->body;
        $note = $note !== null && trim($note) !== '' ? trim($note) : null;

        $image = $post->addMediaFromString($this->draw($post->project, $post->platform, $brief, $note, $post->image_reference_ids ?? []))
            ->usingFileName(Str::uuid().'.jpg')
            ->withCustomProperties(['brief' => $brief, 'note' => $note])
            ->toMediaCollection('images');

        if ($post->image_media_id === null) {
            $post->update(['image_media_id' => $image->id]);
        }

        return $image;
    }

    /**
     * One image for a post on this network, as JPEG bytes: drawn in the
     * network's format and the user's own style, from the project's visual
     * identity library: its logo, a few style examples, and the illustrations
     * picked for this post. Shared with `GenerateSocialPost`, which draws once
     * for a client win's two siblings.
     *
     * @param  array<int, int>  $illustrationIds  the project's illustrations picked for this post
     */
    public function draw(Project $project, SocialPlatform $platform, string $brief, ?string $note = null, array $illustrationIds = []): string
    {
        $illustrations = $project->brandAssets(BrandAssetKind::Illustration)
            ->filter(fn (Media $media): bool => in_array($media->id, $illustrationIds, true))
            ->take(self::MAX_PER_KIND);

        $references = collect([$project->logo()])
            ->filter()
            ->concat($project->brandAssets(BrandAssetKind::Example)->sortByDesc('id')->take(self::MAX_PER_KIND))
            ->concat($illustrations)
            ->values()
            ->all();

        return (new ImageGenerator(
            $project,
            "a {$platform->label()} post",
            $brief,
            $note,
            $platform->imageFormat(),
            $project->getAttribute($platform->instructionsColumn()),
            $references,
        ))->generate();
    }
}
