<?php

namespace App\Actions;

use App\Enums\SocialPostStatus;
use App\Models\SocialPost;

/**
 * "I posted it": the only publish step X has, since Eveil does not pay for
 * X's API. The user copied the draft, posted it on x.com, and pasted the
 * post's URL back. Its status id is kept so a future API integration could
 * still read the post's numbers.
 */
class MarkSocialPostPublished
{
    public function handle(SocialPost $post, string $url): void
    {
        preg_match('~/status/(\d+)~', $url, $match);

        $post->update([
            'status' => SocialPostStatus::Published,
            'url' => $url,
            'external_id' => $match[1] ?? null,
            'published_at' => now(),
            'last_error' => null,
        ]);
    }
}
