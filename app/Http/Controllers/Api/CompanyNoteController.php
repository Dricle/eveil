<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\NoteRequest;
use App\Http\Resources\NoteResource;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * A company's timeline, as on its sheet in the app. A note written through
 * the API has no author: a token belongs to the organization, not a person.
 */
class CompanyNoteController extends Controller
{
    public function store(NoteRequest $request, int $company): JsonResponse
    {
        $note = Company::query()->findOrFail($company)->notes()->create([
            'user_id' => null,
            'body' => $request->string('body')->value(),
        ]);

        return NoteResource::make($note)->response()->setStatusCode(201);
    }

    public function destroy(int $company, int $note): Response
    {
        Company::query()->findOrFail($company)->notes()->findOrFail($note)->delete();

        return response()->noContent();
    }
}
