<?php

namespace App\Http\Controllers;

use App\Enums\EmailStatus;
use App\Http\Resources\ContactResource;
use App\Models\Lead;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The people the searches turned up, and how much their address can be trusted.
 * Nothing is hidden behind a "verified" label: an address guessed from a
 * pattern says so, because the person sending is the one whose domain takes the
 * complaints.
 */
class ContactController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('email_status')->toString();

        $contacts = Lead::query()
            ->with('company')
            ->whereNull('erased_at')
            ->when($request->integer('company'), fn ($query, int $id) => $query->where('company_id', $id))
            ->when($status !== '', fn ($query) => $query->where('email_status', $status))
            // An address that will never be sent to is not a contact — it is
            // kept for the record and shown only when asked for.
            ->when($status === '', fn ($query) => $query->where('email_status', '!=', EmailStatus::Invalid))
            ->orderByRaw("case email_status when 'valid' then 0 when 'unknown' then 1 when 'risky' then 2 else 3 end")
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('leads/Contacts', [
            'contacts' => ContactResource::collection($contacts),
            'filters' => [
                'email_status' => $status ?: null,
                'company' => $request->integer('company') ?: null,
            ],
            'counts' => Lead::query()
                ->whereNull('erased_at')
                ->selectRaw('email_status, count(*) as total')
                ->groupBy('email_status')
                ->pluck('total', 'email_status'),
        ]);
    }
}
