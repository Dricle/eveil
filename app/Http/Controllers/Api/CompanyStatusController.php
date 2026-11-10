<?php

namespace App\Http\Controllers\Api;

use App\Actions\SetOutreachStatus;
use App\Enums\OutreachStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StatusRequest;
use App\Models\Company;
use Illuminate\Http\Response;

/**
 * A CRM closing a deal with a business rather than a person. The verdict
 * travels down to everyone at the company, as in the app.
 */
class CompanyStatusController extends Controller
{
    public function __construct(private SetOutreachStatus $setStatus) {}

    public function update(StatusRequest $request, int $company): Response
    {
        $company = Company::query()->findOrFail($company);

        $this->setStatus->forCompany($company, OutreachStatus::from($request->string('status')->value()));
        $this->setStatus->resolveAttentionForCompany($company);

        return response()->noContent();
    }
}
