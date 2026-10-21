<?php

namespace App\Http\Controllers\Composio;

use App\Actions\Composio\DisconnectIntegration;
use App\Enums\ComposioToolkit;
use App\Enums\IntegrationStatus;
use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Models\Project;
use App\Services\Composio\ComposioClient;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

/**
 * Connects any `ComposioToolkit` through Composio's hosted authorization page. Same
 * shape as `LinkedinOAuthController`: the redirect is reached from inside a
 * project, the callback is the one fixed `oauth/` URL every toolkit shares,
 * and what ties the two together travels in the session.
 *
 * The connection is recorded as a pending `Integration` before the user
 * leaves, and the callback only ever checks THAT row, so a forged callback
 * has nothing to point at. What the toolkit becomes once connected is the
 * toolkit's business (`ComposioToolkit::connected()`).
 */
class ComposioOAuthController extends Controller
{
    public function redirect(Request $request, CurrentProject $currentProject, ComposioClient $composio, DisconnectIntegration $disconnect, ComposioToolkit $toolkit): RedirectResponse
    {
        $organization = $currentProject->organization();

        // Attempts abandoned on Composio's page never come back to say so.
        // Only the stale ones: a teammate may be authorizing right now.
        $organization->integrations()
            ->where('toolkit', $toolkit)
            ->where('status', IntegrationStatus::Pending)
            ->where('created_at', '<', now()->subHour())
            ->each(fn (Integration $stale) => $disconnect->handle($stale));

        try {
            $link = $composio->connectionLink($toolkit, $organization, route('oauth.composio.callback'));
        } catch (Throwable $e) {
            return to_route($toolkit->settingsRoute())->with('status', "{$toolkit->label()} connection could not start: {$e->getMessage()}");
        }

        $integration = $organization->integrations()->create([
            'toolkit' => $toolkit,
            'connected_account_id' => $link['connected_account_id'],
            'status' => IntegrationStatus::Pending,
        ]);

        $request->session()->put('composio_integration_id', $integration->id);
        $request->session()->put('composio_project_id', $currentProject->getOrFail()->id);

        return redirect($link['redirect_url']);
    }

    public function callback(Request $request, ComposioClient $composio, DisconnectIntegration $disconnect): RedirectResponse
    {
        $integrationId = $request->session()->pull('composio_integration_id');
        $projectId = $request->session()->pull('composio_project_id');

        $project = $projectId !== null ? Project::query()->find((int) $projectId) : null;
        $integration = $integrationId !== null ? Integration::query()->find((int) $integrationId) : null;

        if ($project === null || $integration === null || $integration->organization_id !== $project->organization_id) {
            return to_route('app.home')->with('status', 'Connection failed: the request could not be verified.');
        }

        $toolkit = $integration->toolkit;

        try {
            $status = $composio->connectionStatus($integration);

            if ($status !== 'ACTIVE') {
                throw new RuntimeException("it was not authorized (status {$status})");
            }

            $integration->update(['status' => IntegrationStatus::Active, 'last_error' => null]);

            $message = $toolkit->connected($integration, $project);
        } catch (Throwable $e) {
            $disconnect->handle($integration);

            return to_route($toolkit->settingsRoute(), ['project' => $project])->with('status', "{$toolkit->label()} connection failed: {$e->getMessage()}");
        }

        return to_route($toolkit->settingsRoute(), ['project' => $project])->with('status', $message);
    }
}
