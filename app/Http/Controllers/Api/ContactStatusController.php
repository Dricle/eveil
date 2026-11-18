<?php

namespace App\Http\Controllers\Api;

use App\Actions\SetOutreachStatus;
use App\Enums\OutreachStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StatusRequest;
use App\Models\Lead;
use Illuminate\Http\Response;

/**
 * A CRM telling Eveil how a conversation ended: a deal won months later is
 * dated in `won_at` and counted for the campaign that opened it. Same verdict
 * as the app's status menu, travelling up to the company; never on someone
 * who asked to be forgotten.
 */
class ContactStatusController extends Controller
{
    public function __construct(private SetOutreachStatus $setStatus) {}

    public function update(StatusRequest $request, int $contact): Response
    {
        $lead = Lead::query()->whereNull('erased_at')->findOrFail($contact);

        $this->setStatus->forLead($lead, OutreachStatus::from($request->string('status')->value()));
        $this->setStatus->resolveAttentionForLead($lead);

        return response()->noContent();
    }
}
