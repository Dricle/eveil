<?php

namespace App\Jobs;

use App\Models\Webhook;
use App\Services\Webhooks\WebhookSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/**
 * One event to one endpoint, retried over about three hours: a CRM flow being
 * redeployed for ten minutes must not lose the reply that came in meanwhile.
 * The payload's `id` stays the same across retries, so a receiver that got
 * the first attempt after all can drop the second.
 */
class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** Deleted endpoint: nothing left to deliver to. */
    public bool $deleteWhenMissingModels = true;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public Webhook $webhook, public array $payload) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 1800, 7200];
    }

    public function handle(WebhookSender $sender): void
    {
        // Switched off while this waited to retry: the user has to test it
        // back on, and the queued events are dropped rather than replayed.
        if ($this->webhook->disabled_at !== null) {
            return;
        }

        $error = $sender->send($this->webhook, $this->payload);

        if ($error !== null) {
            throw new RuntimeException("Webhook {$this->webhook->id}: {$error}");
        }
    }
}
