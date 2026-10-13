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
    public function store(NoteRequest $request, int $contact): JsonResponse
    {
        $note = Lead::query()->whereNull('erased_at')->findOrFail($contact)->notes()->create([
            'user_id' => null,
            'body' => $request->string('body')->value(),
        ]);

        return NoteResource::make($note)->response()->setStatusCode(201);
    }

    public function destroy(int $contact, int $note): Response
    {
        Lead::query()->whereNull('erased_at')->findOrFail($contact)->notes()->findOrFail($note)->delete();

        return response()->noContent();
    }
}
