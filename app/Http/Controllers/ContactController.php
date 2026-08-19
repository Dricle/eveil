<?php

namespace App\Http\Controllers;

use App\Enums\EmailStatus;
use App\Http\Resources\ContactResource;
use App\Http\Resources\ContactSheetResource;
use App\Models\Lead;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The people the searches turned up, and how much their address can be trusted.
 * Nothing is hidden behind a "verified" label: an address guessed from a
 * pattern says so, because the person sending is the one whose domain takes the
 * complaints.
 *
 * Sorting and filtering are the database's job — the list is paginated, so a
 * column sorted in the browser would only sort the page you can see.
 */
class ContactController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('email_status')->value();
        $source = $request->string('email_source')->value();
        $search = $request->string('search')->trim()->value();

        /** @var array<string, string|null> $columns */
        $columns = $request->collect('filter')
            ->only(Lead::FILTERS)
            ->map(fn ($value): ?string => is_string($value) ? trim($value) : null)
            ->all();

        $contacts = Lead::query()
            ->with('company')
            ->whereNull('erased_at')
            ->matching($search)
            ->whereColumns($columns)
            ->when($request->integer('company'), fn ($query, int $id) => $query->where('company_id', $id))
            ->when($status !== '', fn ($query) => $query->where('email_status', $status))
            ->when($source !== '', fn ($query) => $query->where('email_source', $source))
            // An address that will never be sent to is not a contact — it is
            // kept for the record and shown only when asked for. An address
            // nobody has checked yet is a different thing and stays on the
            // list: an imported row has no verdict until something sends to it.
            ->when($status === '', fn ($query) => $query->where(
                fn ($inner) => $inner->whereNull('email_status')->orWhere('email_status', '!=', EmailStatus::Invalid),
            ))
            ->sorted($request->string('sort')->value(), $request->string('direction')->value())
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('leads/Contacts', [
            'contacts' => ContactResource::collection($contacts),
            // Flashed by the importer, so it appears once on the list it just
            // changed and is gone on the next request.
            'import' => $request->session()->get('import'),
            'filters' => [
                'email_status' => $status ?: null,
                'email_source' => $source ?: null,
                'company' => $request->integer('company') ?: null,
                'search' => $search ?: null,
                'filter' => array_filter($columns, fn (?string $value): bool => $value !== null && $value !== ''),
                'sort' => $request->string('sort')->value() ?: null,
                'direction' => $request->string('direction')->value() ?: null,
            ],
            'counts' => Lead::query()
                ->whereNull('erased_at')
                ->selectRaw('email_status, count(*) as total')
                ->groupBy('email_status')
                ->pluck('total', 'email_status'),
        ]);
    }

    /**
     * One person, everything known about them. A drill-down rather than a sixth
     * nav entry: this is where you land from the list, from the inbox, or from a
     * campaign, and never somewhere you go on purpose.
     *
     * An erased lead answers 404. The row survives erasure by design — it is
     * what stops the next discovery run finding her again — but there is nothing
     * left on it to show, and a page of empty fields would invite somebody to
     * fill them back in.
     */
    public function show(int $contact): Response
    {
        $lead = Lead::query()
            ->whereNull('erased_at')
            ->with([
                'company.evaluations.targetProfile',
                'campaignLeads.campaign',
                'campaignLeads.emailAccount',
                'messages' => fn ($messages) => $messages->orderBy('id'),
            ])
            ->findOrFail($contact);

        return Inertia::render('leads/Contact', [
            'contact' => ContactSheetResource::make($lead),
        ]);
    }
}
