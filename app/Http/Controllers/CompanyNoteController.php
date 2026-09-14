<?php

namespace App\Http\Controllers;

use App\Http\Requests\NoteRequest;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;

/**
 * A company's own timeline: free text a person typed by hand, never sent
 * anywhere and never read by an agent - "spoke to their ops manager, wants
 * a demo next week". The account-level equivalent of `LeadNoteController`.
 */
class CompanyNoteController extends Controller
{
    public function store(NoteRequest $request, int $company): RedirectResponse
    {
        Company::query()
            ->findOrFail($company)
            ->notes()
            ->create([
                'user_id' => $request->user()->id,
                'body' => $request->string('body')->value(),
            ]);

        return back();
    }

    public function destroy(int $company, int $note): RedirectResponse
    {
        Company::query()
            ->findOrFail($company)
            ->notes()
            ->findOrFail($note)
            ->delete();

        return back();
    }
}
