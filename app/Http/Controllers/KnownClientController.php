<?php

namespace App\Http\Controllers;

use App\Actions\MarkKnownClients;
use App\Http\Requests\MarkKnownClientsRequest;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;

/**
 * Clients the user already has, told to the app before a search run finds
 * them: a button on Companies, never a section of its own, same reasoning as
 * `DiscoveryLinkController` and `LeadImportController`.
 */
class KnownClientController extends Controller
{
    public function __construct(private MarkKnownClients $markKnownClients, private CurrentProject $currentProject) {}

    public function store(MarkKnownClientsRequest $request): RedirectResponse
    {
        $report = $this->markKnownClients->handle($this->currentProject->getOrFail(), $request->input('entries'));

        return back()->with('known_clients', $report);
    }
}
