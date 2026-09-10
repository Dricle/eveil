<?php

namespace App\Http\Controllers;

use App\Actions\SetOutreachStatus;
use App\Enums\OutreachStatus;
use App\Http\Requests\CompanyBulkStatusRequest;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;

/**
 * Where several companies stand, in one go: the bulk toolbar's "Set aside" acts
 * on a whole selection, and writing that one company at a time would be as many
 * round trips as rows selected.
 *
 * Ids come from a form, and the project scope is what makes them safe: a
 * company belonging to somebody else's project is simply not found, the same
 * guarantee `CompanyApprovalController` relies on.
 */
class CompanyBulkStatusController extends Controller
{
    public function __construct(private SetOutreachStatus $setStatus) {}

    public function update(CompanyBulkStatusRequest $request): RedirectResponse
    {
        $status = OutreachStatus::from($request->string('status')->value());

        Company::query()
            ->whereIn('id', $request->collect('companies')->all())
            ->get()
            ->each(function (Company $company) use ($status): void {
                $this->setStatus->forCompany($company, $status);
                $this->setStatus->resolveAttentionForCompany($company);
            });

        return back();
    }
}
