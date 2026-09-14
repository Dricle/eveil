<?php

namespace App\Http\Controllers;

use App\Http\Requests\NoteRequest;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;

/**
 * A lead's own timeline: free text a person typed by hand, never sent
 * anywhere and never read by an agent - "called today, booked a meeting for
 * the 20th". Reached from the contact sheet and from the inbox's side panel,
 * so both stay in step automatically.
 */
class LeadNoteController extends Controller
{
    public function store(NoteRequest $request, int $contact): RedirectResponse
    {
        Lead::query()
            ->whereNull('erased_at')
            ->findOrFail($contact)
            ->notes()
            ->create([
                'user_id' => $request->user()->id,
                'body' => $request->string('body')->value(),
            ]);

        return back();
    }

    public function destroy(int $contact, int $note): RedirectResponse
    {
        Lead::query()
            ->whereNull('erased_at')
            ->findOrFail($contact)
            ->notes()
            ->findOrFail($note)
            ->delete();

        return back();
    }
}
