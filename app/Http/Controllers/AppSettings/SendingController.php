<?php

namespace App\Http\Controllers\AppSettings;

use App\Http\Controllers\Controller;
use App\Http\Requests\AppSettings\SendingRequest;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The pace of outreach, instance-wide: what turns a day's allowance into mail
 * that leaves the way a person's does, and the circuit breaker that stops it
 * on its own when bounces say a mailbox is burning its reputation.
 */
class SendingController extends Controller
{
    public function __construct(private Settings $settings) {}

    public function edit(): Response
    {
        $sending = $this->settings->array('sending');

        return Inertia::render('app-settings/Sending', [
            'sending' => [
                'window_start' => $sending['window_start'],
                'window_end' => $sending['window_end'],
                'min_gap_minutes' => $sending['min_gap_minutes'],
                'max_bounce_rate' => $sending['max_bounce_rate'],
            ],
        ]);
    }

    public function update(SendingRequest $request): RedirectResponse
    {
        $this->settings->set('sending', $request->validated());

        return to_route('app-settings.sending.edit')->with('status', 'Sending saved.');
    }
}
