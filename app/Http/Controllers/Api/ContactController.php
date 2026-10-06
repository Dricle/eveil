<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ContactStoreRequest;
use App\Http\Resources\ContactResource;
use App\Imports\LeadsImport;
use App\Models\Lead;
use App\Support\CurrentProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The people found or imported. An erased person is left out, as in the app:
 * the row survives only so discovery never finds them again.
 */
class ContactController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ContactResource::collection(
            Lead::query()->with('company')->whereNull('erased_at')->orderBy('id')->paginate(50)
        );
    }

    public function show(int $contact): ContactResource
    {
        return ContactResource::make(
            Lead::query()->with('company')->whereNull('erased_at')->findOrFail($contact)
        );
    }

    /**
     * The CSV import without the file: same rules (an email or a LinkedIn
     * URL, no one who asked to be forgotten, nothing suppressed), same report.
     */
    public function store(ContactStoreRequest $request, CurrentProject $currentProject): JsonResponse
    {
        $import = new LeadsImport($currentProject->getOrFail());

        foreach ($request->validated('contacts') as $index => $contact) {
            $import->add($contact, $index + 1);
        }

        return response()->json($import->report());
    }
}
