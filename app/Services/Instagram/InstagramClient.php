<?php

namespace App\Services\Instagram;

use App\Models\Integration;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Services\Composio\ComposioClient;
use App\Services\Social\SocialClientInterface;
use Illuminate\Support\Collection;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Instagram's driver, two ways in, one account:
 * - the organization's own Meta app (`InstagramGraphApi`), whenever the
 *   account has a live token from it: it always takes priority;
 * - otherwise Composio's Instagram toolkit and its managed Meta app, already
 *   approved for publishing, so nobody has to create an app or be a tester.
 *   The image goes up as a file there, so no public URL is needed.
 * Business and Creator accounts only: Instagram publishes nothing for a
 * personal one.
 *
 * On an Instagram `SocialAccount`, `external_id` is the professional
 * account's own id (`user_id`), the same through either app, which is what
 * lets one account be connected both ways.
 */
class InstagramClient implements SocialClientInterface
{
    public function __construct(private ComposioClient $composio, private InstagramGraphApi $graph) {}

    /**
     * Who the connected account is.
     *
     * @return array{id: string, username: string, name: string}
     */
    public function profile(Integration $integration): array
    {
        $profile = $this->composio->execute($integration, 'INSTAGRAM_GET_USER_INFO', ['fields' => 'user_id,id,username,name']);

        // `user_id` is the account's own id whichever app asks, `id` only
        // Composio's app's view of it: see `InstagramGraphApi::profile()`.
        $id = (string) ($this->field($profile, 'user_id') ?: $this->field($profile, 'id'));
        $username = (string) $this->field($profile, 'username');

        if ($id === '' || $username === '') {
            throw new RuntimeException('Instagram did not say which account was connected.');
        }

        return ['id' => $id, 'username' => $username, 'name' => (string) ($this->field($profile, 'name') ?: $username)];
    }

    /**
     * Uploads the image, creates the media container with the caption, then
     * publishes it. The URI is Instagram's media id.
     *
     * @return array{uri: string, url: string}
     */
    public function publish(SocialAccount $account, string $text, ?string $language = null, ?Media $image = null): ?array
    {
        if ($image === null) {
            throw new RuntimeException('An Instagram post needs an image.');
        }

        if ($account->publishesThroughOwnApp()) {
            return $this->graph->publish($account, $text, $image);
        }

        $integration = $this->integration($account);

        $file = $this->composio->uploadFile($integration, 'INSTAGRAM_POST_IG_USER_MEDIA', $image->file_name, 'image/jpeg', (string) stream_get_contents($image->stream()));

        $container = $this->composio->execute($integration, 'INSTAGRAM_POST_IG_USER_MEDIA', [
            'ig_user_id' => $account->external_id,
            'caption' => $text,
            'image_file' => $file,
        ]);

        $media = $this->composio->execute($integration, 'INSTAGRAM_POST_IG_USER_MEDIA_PUBLISH', [
            'ig_user_id' => $account->external_id,
            'creation_id' => (string) $this->field($container, 'id'),
        ]);

        $mediaId = (string) $this->field($media, 'id');

        if ($mediaId === '') {
            throw new RuntimeException('Instagram published the post but returned no id.');
        }

        return ['uri' => $mediaId, 'url' => $this->permalink($account, $integration, $mediaId)];
    }

    /**
     * One read per post, through the connection its account goes through. A
     * post whose account or connection is gone, or that Instagram refuses to
     * read, is skipped.
     *
     * @param  Collection<int, SocialPost>  $posts
     * @return array<int, int>
     */
    public function likeCounts(Collection $posts): array
    {
        $counts = [];

        foreach ($posts as $post) {
            $account = $post->socialAccount;

            if ($account === null || $post->external_id === null) {
                continue;
            }

            try {
                if ($account->publishesThroughOwnApp()) {
                    $counts[$post->id] = $this->graph->likeCount($account, $post->external_id);

                    continue;
                }

                if ($account->integration === null) {
                    continue;
                }

                $media = $this->composio->execute($account->integration, 'INSTAGRAM_GET_IG_MEDIA', [
                    'ig_media_id' => $post->external_id,
                    'fields' => 'like_count',
                ]);
            } catch (Throwable) {
                continue;
            }

            $counts[$post->id] = (int) $this->field($media, 'like_count');
        }

        return $counts;
    }

    private function integration(SocialAccount $account): Integration
    {
        return $account->integration ?? throw new RuntimeException('This Instagram account has no working connection: connect it again, through Composio or your own Meta app.');
    }

    /**
     * The post's public link. Best effort: the profile when Instagram does not
     * give it back, rather than failing a post that did go out.
     */
    private function permalink(SocialAccount $account, Integration $integration, string $mediaId): string
    {
        try {
            $media = $this->composio->execute($integration, 'INSTAGRAM_GET_IG_MEDIA', [
                'ig_media_id' => $mediaId,
                'fields' => 'permalink',
            ]);
        } catch (Throwable) {
            $media = [];
        }

        $permalink = $this->field($media, 'permalink');

        return is_string($permalink) && $permalink !== '' ? $permalink : "https://www.instagram.com/{$account->handle}/";
    }

    /**
     * A field of a tool's answer, which Composio sometimes nests one level
     * further under `data`.
     *
     * @param  array<string, mixed>  $answer
     */
    private function field(array $answer, string $key): mixed
    {
        return $answer[$key] ?? (is_array($answer['data'] ?? null) ? ($answer['data'][$key] ?? null) : null);
    }
}
