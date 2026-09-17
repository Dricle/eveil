<?php

namespace App\Http\Controllers;

use App\Actions\DeleteAllLeads;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;

/**
 * The project settings "danger zone": every contact found for the project,
 * gone at once, so discovery can start over from nothing. Separate from
 * `CompanyBulkDeleteController` on purpose - a company and its leads reset
 * independently, see `DeleteAllLeads`.
 */
class ContactBulkDeleteController extends Controller
{
    public function __construct(private CurrentProject $currentProject) {}

    public function destroy(DeleteAllLeads $action): RedirectResponse
    {
        $action->handle($this->currentProject->getOrFail());

        return back();
    }
}
