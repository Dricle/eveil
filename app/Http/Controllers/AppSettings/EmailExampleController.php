<?php

namespace App\Http\Controllers\AppSettings;

use App\Enums\EmailExampleSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\AppSettings\EmailExampleRequest;
use App\Http\Resources\AppSettings\EmailExampleResource;
use App\Models\EmailExample;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The shared bank of proven emails, and the quality bar a campaign step's
 * own track record has to clear to join it automatically — see
 * `App\Actions\PromoteProvenEmails`, the thing that actually promotes one.
 * This screen only manages the bank and its threshold, never promotes
 * anything itself.
 */
class EmailExampleController extends Controller
{
    public function __construct(private Settings $settings) {}

    public function index(): Response
    {
        return Inertia::render('app-settings/EmailExamples', [
            'examples' => EmailExampleResource::collection(
                EmailExample::query()->latest('id')->get()
            ),
            'thresholds' => [
                'min_sends' => $this->settings->int('email_examples.min_sends'),
                'min_positive_rate' => $this->settings->float('email_examples.min_positive_rate'),
                'max_unsubscribe_rate' => $this->settings->float('email_examples.max_unsubscribe_rate'),
            ],
        ]);
    }

    public function store(EmailExampleRequest $request): RedirectResponse
    {
        [$subject, $body] = $request->subjectAndBody();

        EmailExample::create([
            'subject' => $subject,
            'body' => $body,
            'source' => EmailExampleSource::Manual,
            'added_by_user_id' => $request->user()->id,
        ]);

        return to_route('app-settings.email-examples.index')->with('status', 'Example added.');
    }

    public function destroy(EmailExample $emailExample): RedirectResponse
    {
        $emailExample->delete();

        return to_route('app-settings.email-examples.index')->with('status', 'Example removed.');
    }
}
