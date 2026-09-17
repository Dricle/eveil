<?php

namespace App\Services\Discovery;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The one case a headless renderer actually fixes: a page that fetched fine
 * but came back a shell (see `PageFetcher::fetch()`, `Harvest::status()`'s
 * `JsOnly`). Not a `DiscoverySourceInterface`: this renders a page, it does
 * not find companies.
 *
 * Off by default, and failing exactly as harmless as never being installed:
 * not configured, a dead container, a timeout, or a challenge it could not
 * solve all return null and `PageFetcher` falls back to the plain fetch it
 * already had. No interface here on purpose - there is one implementation,
 * and building the seam before a second one exists is the mistake the
 * self-hosted renderer note in `.ai/rules/discovery.md` warns against.
 */
class FlareSolverrRenderer
{
    public function render(string $url): ?string
    {
        $base = config('eveil.sources.flaresolverr.url');

        if (! is_string($base) || $base === '') {
            return null;
        }

        $maxTimeout = (int) config('eveil.sources.flaresolverr.max_timeout_ms');

        try {
            $response = Http::timeout((int) ceil($maxTimeout / 1000) + 5)
                ->post(rtrim($base, '/').'/v1', [
                    'cmd' => 'request.get',
                    'url' => $url,
                    'maxTimeout' => $maxTimeout,
                ]);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful() || $response->json('status') !== 'ok') {
            return null;
        }

        $html = $response->json('solution.response');

        return is_string($html) && $html !== '' ? $html : null;
    }
}
