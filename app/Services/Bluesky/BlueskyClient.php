<?php

namespace App\Services\Bluesky;

use App\Models\SocialAccount;
use App\Support\HtmlText;
use DOMXPath;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Bluesky's own API (AT Protocol), free and unmetered. Authenticates with an
 * app password rather than OAuth: atproto OAuth needs a public HTTPS client
 * metadata URL and DPoP keys, which a self-hosted instance on a laptop does
 * not have, while an app password is revocable from the user's own settings
 * and cannot change the account.
 *
 * A fresh session per publish instead of storing the short-lived JWT: posts
 * go out a few times a week at most, far below `createSession`'s rate limit.
 */
class BlueskyClient
{
    // ponytail: accounts hosted on Bluesky's own PDS only. A self-hosted PDS
    // needs its URL resolved from the DID document, add when someone asks.
    private const SERVICE = 'https://bsky.social';

    // Reads that need no session: profiles and post counts.
    private const PUBLIC_API = 'https://public.api.bsky.app';

    // Bluesky's own limit on an uploaded image.
    private const MAX_THUMB_BYTES = 1_000_000;

    public function __construct(private HtmlText $htmlText) {}

    /**
     * Checks a handle and app password and returns who they belong to.
     *
     * @return array{did: string, handle: string, display_name: string}
     */
    public function connect(string $handle, string $appPassword): array
    {
        $session = $this->session($handle, $appPassword);

        $displayName = Http::get(self::PUBLIC_API.'/xrpc/app.bsky.actor.getProfile', ['actor' => $session['did']])
            ->json('displayName');

        return [
            'did' => $session['did'],
            'handle' => $session['handle'],
            'display_name' => is_string($displayName) && $displayName !== '' ? $displayName : $session['handle'],
        ];
    }

    /**
     * Publishes as the account and returns the post's at:// URI and its
     * public URL. Links and hashtags are made clickable, and the first link
     * gets a preview card: Bluesky does neither on its own for a post made
     * through the API.
     *
     * @return array{uri: string, url: string}
     */
    public function publish(SocialAccount $account, string $text, ?string $language = null): array
    {
        $session = $this->session($account->external_id, $account->secret);

        $record = [
            '$type' => 'app.bsky.feed.post',
            'text' => $text,
            'createdAt' => now()->toIso8601ZuluString('millisecond'),
        ];

        $facets = $this->facets($text);

        if ($facets !== []) {
            $record['facets'] = $facets;
        }

        if ($language !== null) {
            $record['langs'] = [$language];
        }

        $link = $this->firstLink($text);
        $card = $link === null ? null : $this->linkCard($link, $session['accessJwt']);

        if ($card !== null) {
            $record['embed'] = ['$type' => 'app.bsky.embed.external', 'external' => $card];
        }

        $response = Http::withToken($session['accessJwt'])
            ->post(self::SERVICE.'/xrpc/com.atproto.repo.createRecord', [
                'repo' => $session['did'],
                'collection' => 'app.bsky.feed.post',
                'record' => $record,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("Bluesky refused the post: HTTP {$response->status()} {$response->body()}");
        }

        $uri = (string) $response->json('uri');

        return ['uri' => $uri, 'url' => $this->postUrl($session['handle'], $uri)];
    }

    /**
     * Like counts by post URI. Public, no session needed; Bluesky caps one
     * call at 25 URIs.
     *
     * @param  array<int, string>  $uris
     * @return array<string, int>
     */
    public function likeCounts(array $uris): array
    {
        $counts = [];

        foreach (array_chunk($uris, 25) as $chunk) {
            $query = implode('&', array_map(fn (string $uri): string => 'uris='.urlencode($uri), $chunk));

            $response = Http::get(self::PUBLIC_API.'/xrpc/app.bsky.feed.getPosts?'.$query);

            foreach ((array) $response->json('posts', []) as $post) {
                if (is_array($post) && isset($post['uri'])) {
                    $counts[(string) $post['uri']] = (int) ($post['likeCount'] ?? 0);
                }
            }
        }

        return $counts;
    }

    /**
     * @return array{accessJwt: string, did: string, handle: string}
     */
    private function session(string $identifier, string $appPassword): array
    {
        $response = Http::post(self::SERVICE.'/xrpc/com.atproto.server.createSession', [
            'identifier' => $identifier,
            'password' => $appPassword,
        ]);

        if (! $response->successful()) {
            throw new SignInRefused("Bluesky refused the sign-in: {$response->json('message', 'HTTP '.$response->status())}");
        }

        return [
            'accessJwt' => (string) $response->json('accessJwt'),
            'did' => (string) $response->json('did'),
            'handle' => (string) $response->json('handle'),
        ];
    }

    /**
     * Rich-text ranges for every link and hashtag. Offsets are UTF-8 BYTES,
     * which is what `PREG_OFFSET_CAPTURE` reports even with the `u` flag.
     *
     * @return array<int, array<string, mixed>>
     */
    private function facets(string $text): array
    {
        $facets = [];

        preg_match_all('~https?://[^\s]+~u', $text, $links, PREG_OFFSET_CAPTURE);

        foreach ($links[0] as [$url, $start]) {
            $url = rtrim($url, '.,;:!?)"\'');

            $facets[] = [
                'index' => ['byteStart' => $start, 'byteEnd' => $start + strlen($url)],
                'features' => [['$type' => 'app.bsky.richtext.facet#link', 'uri' => $url]],
            ];
        }

        preg_match_all('~(?<=^|\s)#(\p{L}[\p{L}\p{N}_]*)~u', $text, $tags, PREG_OFFSET_CAPTURE);

        foreach ($tags[0] as $index => [$tag, $start]) {
            $facets[] = [
                'index' => ['byteStart' => $start, 'byteEnd' => $start + strlen($tag)],
                'features' => [['$type' => 'app.bsky.richtext.facet#tag', 'tag' => $tags[1][$index][0]]],
            ];
        }

        return $facets;
    }

    private function firstLink(string $text): ?string
    {
        return preg_match('~https?://[^\s]+~u', $text, $match) === 1
            ? rtrim($match[0], '.,;:!?)"\'')
            : null;
    }

    /**
     * The preview card for a link, built from the page's own Open Graph tags.
     * Best effort: a page that cannot be read still gets posted, just
     * without a card.
     *
     * @return array<string, mixed>|null
     */
    private function linkCard(string $url, string $accessJwt): ?array
    {
        try {
            $html = Http::withUserAgent((string) config('eveil.crawl.user_agent'))
                ->timeout((int) config('eveil.crawl.timeout'))
                ->get($url)
                ->body();
        } catch (Throwable) {
            return null;
        }

        $document = $this->htmlText->document($html);

        if ($document === null) {
            return null;
        }

        $xpath = new DOMXPath($document);
        $meta = fn (string $property): string => trim((string) $xpath->evaluate("string(//meta[@property='{$property}' or @name='{$property}']/@content)"));

        $title = $meta('og:title') ?: trim((string) $xpath->evaluate('string(//title)'));

        if ($title === '') {
            return null;
        }

        $card = ['uri' => $url, 'title' => $title, 'description' => $meta('og:description') ?: $meta('description')];

        $thumb = $this->uploadThumb($meta('og:image'), $url, $accessJwt);

        if ($thumb !== null) {
            $card['thumb'] = $thumb;
        }

        return $card;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function uploadThumb(string $image, string $pageUrl, string $accessJwt): ?array
    {
        if ($image === '') {
            return null;
        }

        try {
            $image = (string) UriResolver::resolve(new Uri($pageUrl), new Uri($image));

            $download = Http::timeout((int) config('eveil.crawl.timeout'))->get($image);
            $mime = strtok((string) $download->header('Content-Type'), ';') ?: '';

            if (! $download->successful() || ! str_starts_with($mime, 'image/') || strlen($download->body()) > self::MAX_THUMB_BYTES) {
                return null;
            }

            $blob = Http::withToken($accessJwt)
                ->withBody($download->body(), $mime)
                ->post(self::SERVICE.'/xrpc/com.atproto.repo.uploadBlob')
                ->json('blob');
        } catch (Throwable) {
            return null;
        }

        return is_array($blob) ? $blob : null;
    }

    /**
     * bsky.app's own URL for a post: the record key is the last segment of
     * its at:// URI.
     */
    private function postUrl(string $handle, string $uri): string
    {
        return "https://bsky.app/profile/{$handle}/post/".basename($uri);
    }
}
