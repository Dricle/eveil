<?php

namespace App\Services\Webhooks;

use App\Models\Webhook;
use App\Support\PublicAddress;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * One POST to one endpoint, and its outcome written on the row.
 *
 * The signature is an HMAC-SHA256 of the raw body with the endpoint's secret,
 * sent as `X-Eveil-Signature: sha256=<hex>`: the same shape GitHub uses, which
 * every receiver already knows how to check. Redirects are not followed, so a
 * public address cannot bounce the request onto a private one.
 */
class WebhookSender
{
    private const TIMEOUT = 10;

    /**
     * The error, or null when the endpoint answered 2xx.
     *
     * @param  array<string, mixed>  $payload
     */
    public function send(Webhook $webhook, array $payload): ?string
    {
        if (! PublicAddress::allows($webhook->url)) {
            return $this->record($webhook, null, 'This address points at a private network.');
        }

        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withoutRedirecting()
                ->withUserAgent('Eveil-Webhook')
                ->withHeaders([
                    'X-Eveil-Event' => $payload['event'],
                    'X-Eveil-Delivery' => $payload['id'],
                    'X-Eveil-Signature' => 'sha256='.hash_hmac('sha256', $body, $webhook->secret),
                ])
                ->withBody($body, 'application/json')
                ->post($webhook->url);
        } catch (ConnectionException $e) {
            return $this->record($webhook, null, $e->getMessage());
        }

        // The status alone, never the body: what an endpoint answers is not
        // ours to store, and on cloud it could be something we should not see.
        return $this->record($webhook, $response->status(), $response->successful() ? null : 'HTTP '.$response->status());
    }

    /**
     * A success clears the failure streak and switches a disabled endpoint
     * back on, which is what makes a successful test the way to revive one. A
     * day of nothing but failures switches it off: the CRM flow was deleted or
     * moved, and retrying every event against it forever helps nobody.
     */
    private function record(Webhook $webhook, ?int $status, ?string $error): ?string
    {
        $failingSince = $error === null ? null : ($webhook->failing_since ?? now());

        $webhook->update([
            'last_delivered_at' => now(),
            'last_status' => $status,
            'last_error' => $error === null ? null : mb_substr($error, 0, 500),
            'failing_since' => $failingSince,
            'disabled_at' => $failingSince?->lte(now()->subDay()) === true ? ($webhook->disabled_at ?? now()) : null,
        ]);

        return $error;
    }
}
