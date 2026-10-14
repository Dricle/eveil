<?php

namespace App\Http\Controllers\Settings;

use App\Enums\WebhookEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\WebhookRequest;
use App\Http\Resources\WebhookResource;
use App\Models\Webhook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The current project's webhooks. The secret is generated here and flashed
 * once, like an API token: only its encrypted copy stays, for signing.
 * No edit: changing where leads go is a delete and a new endpoint, which
 * also rotates the secret.
 */
class WebhookController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('settings/Webhooks', [
            'webhooks' => WebhookResource::collection(Webhook::query()->latest('id')->get()),
            'events' => array_map(fn (WebhookEvent $event): string => $event->value, WebhookEvent::cases()),
            'secret' => $request->session()->get('webhookSecret'),
        ]);
    }

    public function store(WebhookRequest $request): RedirectResponse
    {
        $secret = 'whsec_'.Str::random(40);

        Webhook::query()->create([...$request->validated(), 'secret' => $secret]);

        return to_route('settings.webhooks.index')->with('webhookSecret', $secret);
    }

    public function destroy(int $webhook): RedirectResponse
    {
        Webhook::query()->findOrFail($webhook)->delete();

        return to_route('settings.webhooks.index');
    }
}
