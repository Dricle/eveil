<?php

namespace App\Http\Controllers;

use App\Actions\ChangeComposioKey;
use App\Http\Requests\ComposioKeyRequest;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The organization's own Composio key, which every service it connects
 * through Composio (Instagram first) goes through. Write-only from this
 * page's point of view: the key never travels back to the browser, only
 * whether one is set and how many connections depend on it.
 */
class ComposioKeyController extends Controller
{
    public function __construct(private CurrentProject $currentProject) {}

    public function edit(): Response
    {
        $organization = $this->currentProject->organization();

        return Inertia::render('settings/Composio', [
            'configured' => $organization->composio_api_key !== null,
            'connections' => $organization->integrations()->count(),
        ]);
    }

    public function update(ComposioKeyRequest $request, ChangeComposioKey $change): RedirectResponse
    {
        $change->handle($this->currentProject->organization(), $request->string('api_key')->value());

        return to_route('settings.composio.edit')->with('status', 'Composio API key saved.');
    }

    public function destroy(ChangeComposioKey $change): RedirectResponse
    {
        $change->handle($this->currentProject->organization(), null);

        return to_route('settings.composio.edit')->with('status', 'Composio API key removed, and the services connected through it with it.');
    }
}
