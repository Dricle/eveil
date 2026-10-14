<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use App\Services\Webhooks\WebhookSender;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

/**
 * Sends a `ping` straight away, not queued: the point is to see the n8n flow
 * light up while the user is looking at it, and to read the error now.
 */
class WebhookTestController extends Controller
{
    public function store(WebhookSender $sender, CurrentProject $currentProject, int $webhook): RedirectResponse
    {
        $project = $currentProject->getOrFail();

        $error = $sender->send(Webhook::query()->findOrFail($webhook), [
            'id' => (string) Str::uuid(),
            'event' => 'ping',
            'created_at' => now()->toIso8601String(),
            'project' => ['slug' => $project->slug, 'name' => $project->name],
        ]);

        return $error === null
            ? to_route('settings.webhooks.index')->with('status', 'The endpoint answered.')
            : to_route('settings.webhooks.index')->withErrors(['test' => $error]);
    }
}
