<?php

namespace App\Http\Controllers;

use App\Actions\SetOutreachStatus;
use App\Enums\OutreachStatus;
use App\Http\Requests\StatusRequest;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;

/**
 * Where a company stands, said by the user rather than inferred: already a
 * client, closed, lost, or "not this one". Five of the nine statuses take it out
 * of outreach, which is the whole point: a business somebody already sells to
 * must never receive a cold pitch from the same product.
 *
 * The verdict travels down to every person at the company, because it is one
 * relationship and not two. The row stays whatever it says: deleting it would
 * only mean the next discovery run finds the same company and offers it back.
 */
class CompanyStatusController extends Controller
{
    public function __construct(private SetOutreachStatus $setStatus) {}

    public function update(StatusRequest $request, int $company): RedirectResponse
    {
        $this->setStatus->forCompany(
            Company::query()->findOrFail($company),
            OutreachStatus::from($request->string('status')->value()),
        );

        return back();
    }
}
