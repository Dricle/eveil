<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\RedirectResponse;

/**
 * "Not this one." A competitor, a former employer, a firm somebody already
 * knows — none of that is visible to a score, and the user's verdict outranks
 * one either way.
 *
 * The row stays: deleting it would only mean the next run finds the same
 * company again and offers it back.
 */
class CompanyRejectionController extends Controller
{
    public function store(int $company): RedirectResponse
    {
        Company::query()->findOrFail($company)->update(['rejected_at' => now()]);

        return back();
    }

    public function destroy(int $company): RedirectResponse
    {
        Company::query()->findOrFail($company)->update(['rejected_at' => null]);

        return back();
    }
}
