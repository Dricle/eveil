<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Every company this project found or imported, set-aside ones included: a
 * CRM syncing from here needs to hear that a company became a client or was
 * rejected, which the app's own list hides by default.
 */
class CompanyController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CompanyResource::collection(
            Company::query()
                ->withBestFit()
                ->withCount(['leads as contacts_count' => fn ($query) => $query->whereNull('erased_at')])
                ->with('evaluations.targetProfile')
                ->orderBy('id')
                ->paginate(50)
        );
    }

    public function show(int $company): CompanyResource
    {
        return CompanyResource::make(
            Company::query()
                ->withBestFit()
                ->withCount(['leads as contacts_count' => fn ($query) => $query->whereNull('erased_at')])
                ->with('evaluations.targetProfile')
                ->findOrFail($company)
        );
    }
}
