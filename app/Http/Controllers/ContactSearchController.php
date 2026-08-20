<?php

namespace App\Http\Controllers;

use App\Enums\ContactSearchStatus;
use App\Jobs\FindCompanyContacts;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Going to look for the people at a company. One company when the user asks
 * about one, otherwise every kept company nobody has looked at yet: clicking
 * forty times is work the app should be doing.
 */
class ContactSearchController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $companies = Company::query()
            ->contactable()
            ->when(
                $request->integer('company'),
                fn ($query, int $id) => $query->whereKey($id),
                // Never asked before. Asking again for a company that came back
                // empty is a deliberate click on that row, not a side effect of
                // a bulk button.
                fn ($query) => $query->whereNull('contacts_status'),
            )
            ->get();

        if ($request->integer('company') && $companies->isEmpty()) {
            abort(404);
        }

        foreach ($companies as $company) {
            $company->update(['contacts_status' => ContactSearchStatus::Queued]);

            FindCompanyContacts::dispatch($company, $request->boolean('guess_generic'));
        }

        return back();
    }
}
