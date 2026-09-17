<?php

namespace App\Http\Controllers;

use App\Actions\DeleteAllCompanies;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;

/**
 * The project settings "danger zone": every company found for the project,
 * gone at once, so discovery can start over from nothing. Not a selection
 * from the bulk toolbar (`CompanyBulkStatusController`) - there is no partial
 * version of this, only all or none.
 */
class CompanyBulkDeleteController extends Controller
{
    public function __construct(private CurrentProject $currentProject) {}

    public function destroy(DeleteAllCompanies $action): RedirectResponse
    {
        $action->handle($this->currentProject->getOrFail());

        return back();
    }
}
