<?php

namespace App\Http\Controllers\AppSettings;

use App\Http\Controllers\Controller;
use App\Http\Requests\AppSettings\EmailExampleThresholdRequest;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;

/**
 * The quality bar itself. The screen showing the current values is owned by
 * `EmailExampleController::index()`, same split as `AutoTopUpController`
 * against the billing page it saves back to.
 */
class EmailExampleThresholdController extends Controller
{
    public function __construct(private Settings $settings) {}

    public function update(EmailExampleThresholdRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->settings->set('email_examples.min_sends', $validated['min_sends']);
        $this->settings->set('email_examples.min_positive_rate', $validated['min_positive_rate']);
        $this->settings->set('email_examples.max_unsubscribe_rate', $validated['max_unsubscribe_rate']);

        return to_route('app-settings.email-examples.index')->with('status', 'Threshold saved.');
    }
}
