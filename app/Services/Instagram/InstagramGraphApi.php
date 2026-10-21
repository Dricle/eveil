<?php

namespace App\Services\Instagram;

use App\Models\PlatformApp;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Instagram's own API (Instagram API with Instagram Login), through the
 * organization's own Meta app, without Composio: the OAuth dance, keeping
 * the 60-day token alive, publishing and reading likes.
 *
 * The one thing this path needs that Composio does not: Meta fetches the
 * image itself, from a URL, so the media disk must be reachable from the
 * internet (a public bucket, or the instance behind a public `APP_URL`).
 */
class InstagramGraphApi
{
    private const GRAPH = 'https://graph.instagram.com';

    private const VERSION = 'v23.0';

    private const SCOPES = 'instagram_business_basic,instagram_business_content_publish';

    public function authorizationUrl(PlatformApp $app, string $redirectUri, string $state): string
    {
        return 'https://www.instagram.com/oauth/authorize?'.http_build_query([
            'client_id' => $app->client_id,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'state' => $state,
        ]);
    }

    /**
     * Trades the callback's code for a long-lived token, in Meta's two steps:
     * a short-lived token first, then the 60-day one.
     *
     * @return array{token: string, expires_in: int}
     */
    public function exchangeCode(PlatformApp $app, string $code, string $redirectUri): array
    {
        $short = Http::asForm()->post('https://api.instagram.com/oauth/access_token', [
            'client_id' => $app->client_id,
            'client_secret' => $app->client_secret,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
            // Instagram appends `#_` to the code it hands back.
            'code' => preg_replace('/#_$/', '', $code),
        ]);

        $this->ensureSuccessful($short->successful(), $short->status(), $short->body());

        $shortToken = (string) ($short->json('access_token') ?? $short->json('data.0.access_token'));

        $long = Http::get(self::GRAPH.'/access_token', [
            'grant_type' => 'ig_exchange_token',
            'client_secret' => $app->client_secret,
            'access_token' => $shortToken,
        ]);

        $this->ensureSuccessful($long->successful(), $long->status(), $long->body());

        return ['token' => (string) $long->json('access_token'), 'expires_in' => (int) $long->json('expires_in')];
    }

    /**
     * A fresh 60-day token for one at least a day old and not expired yet.
     * Needs no app secret: the token is enough.
     *
     * @return array{token: string, expires_in: int}
     */
    public function refresh(string $token): array
    {
        $response = Http::get(self::GRAPH.'/refresh_access_token', [
            'grant_type' => 'ig_refresh_token',
            'access_token' => $token,
        ]);

        $this->ensureSuccessful($response->successful(), $response->status(), $response->body());

        return ['token' => (string) $response->json('access_token'), 'expires_in' => (int) $response->json('expires_in')];
    }

    /**
     * Who the token belongs to. `user_id` is the professional account's own
     * id, the same whichever app asks; `id` is scoped to the asking app, so
     * it is never what an account is matched on.
     *
     * @return array{id: string, username: string, name: string}
     */
    public function profile(string $token): array
    {
        $response = Http::get(self::GRAPH.'/'.self::VERSION.'/me', [
            'fields' => 'user_id,username,name',
            'access_token' => $token,
        ]);

        $this->ensureSuccessful($response->successful(), $response->status(), $response->body());

        $username = (string) $response->json('username');

        return [
            'id' => (string) $response->json('user_id'),
            'username' => $username,
            'name' => (string) ($response->json('name') ?: $username),
        ];
    }

    /**
     * Creates the media container from the image's URL, waits for Meta to
     * have fetched it, then publishes.
     *
     * @return array{uri: string, url: string}
     */
    public function publish(SocialAccount $account, string $caption, Media $image): array
    {
        $token = $account->credential();

        $container = Http::asForm()->post(self::GRAPH.'/'.self::VERSION."/{$account->external_id}/media", [
            'image_url' => $image->getTemporaryUrl(now()->addHour()),
            'caption' => $caption,
            'access_token' => $token,
        ]);

        $this->ensureSuccessful($container->successful(), $container->status(), $container->body());

        $containerId = (string) $container->json('id');
        $this->waitUntilReady($containerId, $token);

        $published = Http::asForm()->post(self::GRAPH.'/'.self::VERSION."/{$account->external_id}/media_publish", [
            'creation_id' => $containerId,
            'access_token' => $token,
        ]);

        $this->ensureSuccessful($published->successful(), $published->status(), $published->body());

        $mediaId = (string) $published->json('id');

        return ['uri' => $mediaId, 'url' => $this->permalink($mediaId, $token, $account)];
    }

    public function likeCount(SocialAccount $account, string $mediaId): int
    {
        $response = Http::get(self::GRAPH.'/'.self::VERSION."/{$mediaId}", [
            'fields' => 'like_count',
            'access_token' => $account->credential(),
        ]);

        $this->ensureSuccessful($response->successful(), $response->status(), $response->body());

        return (int) $response->json('like_count');
    }

    /**
     * An image container is usually ready at once; polled briefly anyway,
     * since publishing one that is not refuses the post.
     */
    private function waitUntilReady(string $containerId, string $token): void
    {
        foreach (range(1, 10) as $attempt) {
            $status = (string) Http::get(self::GRAPH.'/'.self::VERSION."/{$containerId}", [
                'fields' => 'status_code',
                'access_token' => $token,
            ])->json('status_code');

            if ($status === 'FINISHED') {
                return;
            }

            if ($status === 'ERROR' || $status === 'EXPIRED') {
                throw new RuntimeException("Instagram could not process the image (status {$status}). Is the media disk reachable from the internet?");
            }

            Sleep::for(2)->seconds();
        }

        throw new RuntimeException('Instagram was still processing the image after 20 seconds.');
    }

    private function permalink(string $mediaId, string $token, SocialAccount $account): string
    {
        try {
            $permalink = Http::get(self::GRAPH.'/'.self::VERSION."/{$mediaId}", [
                'fields' => 'permalink',
                'access_token' => $token,
            ])->json('permalink');
        } catch (Throwable) {
            $permalink = null;
        }

        return is_string($permalink) && $permalink !== '' ? $permalink : "https://www.instagram.com/{$account->handle}/";
    }

    private function ensureSuccessful(bool $successful, int $status, string $body): void
    {
        if (! $successful) {
            throw new RuntimeException("Instagram refused the request: HTTP {$status} {$body}");
        }
    }
}
