<?php

namespace App\Http\Controllers;

use App\Actions\SetOutreachStatus;
use App\Enums\OutreachStatus;
use App\Http\Requests\StatusRequest;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;

/**
 * The same verdict one person at a time, which travels up to their company:
 * winning a deal with somebody wins it with the business they work for.
 *
 * Outreach normally writes this column itself (queued, contacted, replied), and
 * this route is the user overruling it, so every value is accepted rather than
 * only the manual ones.
 */
class ContactStatusController extends Controller
{
    public function __construct(private SetOutreachStatus $setStatus) {}

    public function update(StatusRequest $request, int $contact): RedirectResponse
    {
        $this->setStatus->forLead(
            Lead::query()->findOrFail($contact),
            OutreachStatus::from($request->string('status')->value()),
        );

        return back();
    }
}
