<?php

namespace App\Actions;

use App\Ai\Agents\ImageGenerator;
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
 * plus whatever the user asked to change.
 */
class AddSocialPostImage
{
    public function handle(SocialPost $post, ?string $note = null): Media
    {
        $brief = trim($post->image_brief ?? '') ?: $post->body;

        if ($note !== null && trim($note) !== '') {
            $brief .= "\n\nThe user saw an earlier image for this post and asked for this change: ".trim($note);
        }

        $image = $post->addMediaFromString((new ImageGenerator($post->project, "a {$post->platform->label()} post", $brief))->generate())
            ->usingFileName(Str::uuid().'.jpg')
            ->withCustomProperties(['brief' => $brief])
            ->toMediaCollection('images');

        if ($post->image_media_id === null) {
            $post->update(['image_media_id' => $image->id]);
        }

        return $image;
    }
}
