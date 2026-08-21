<?php

namespace App\Http\Controllers;

use App\Actions\ApproveCompanies;
use App\Http\Requests\CompanyApprovalRequest;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;

/**
 * The go-ahead on a company, which is the last human decision before mail
 * leaves: everything after it is paced by the scheduler.
 *
 * Ids come from a form, and the project scope is what makes them safe: a
 * company belonging to somebody else's project is simply not found, so a
 * tampered id approves nothing rather than approving a stranger's row.
 */
class CompanyApprovalController extends Controller
{
    public function __construct(private ApproveCompanies $approve) {}

    public function update(CompanyApprovalRequest $request): RedirectResponse
    {
        $companies = Company::query()
            ->whereIn('id', $request->collect('companies')->all())
            ->get();

        $this->approve->handle($companies, $request->boolean('approved', true));

        return back();
    }
}
