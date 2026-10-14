<?php

namespace App\Http\Resources;

use App\Models\Webhook;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Never the secret: it was shown once, at creation.
 *
 * @mixin Webhook
 */
class WebhookResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'events' => $this->events,
            'last_delivered_at' => $this->last_delivered_at?->toIso8601String(),
            'last_status' => $this->last_status,
            'last_error' => $this->last_error,
            'disabled' => $this->disabled_at !== null,
        ];
    }
}
