<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\NoteRequest;
use App\Http\Resources\NoteResource;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * A person's timeline, as on their sheet in the app; never on someone who
 * asked to be forgotten. Like `CompanyNoteController`, a note written
 * through the API has no author.
 */
class ContactNoteController extends Controller
{
    /**
     * Newest first, like the timeline. Not paginated: a timeline is short.
     */
    public function index(int $contact): JsonResponse
    {
        return response()->json(['data' => NoteResource::collection(
            Lead::query()->whereNull('erased_at')->findOrFail($contact)->notes()->with('user')->get()
        )]);
    }

    public function store(NoteRequest $request, int $contact): JsonResponse
    {
        $note = Lead::query()->whereNull('erased_at')->findOrFail($contact)->notes()->create([
            'user_id' => null,
            'body' => $request->string('body')->value(),
        ]);

        return NoteResource::make($note)->response()->setStatusCode(201);
    }

    public function update(NoteRequest $request, int $contact, int $note): NoteResource
    {
        $note = Lead::query()->whereNull('erased_at')->findOrFail($contact)->notes()->with('user')->findOrFail($note);

        $note->update(['body' => $request->string('body')->value()]);

        return NoteResource::make($note);
    }

    public function destroy(int $contact, int $note): Response
    {
        Lead::query()->whereNull('erased_at')->findOrFail($contact)->notes()->findOrFail($note)->delete();

        return response()->noContent();
    }
}
