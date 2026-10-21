<?php

namespace App\Http\Controllers;

use App\Actions\ConnectInstagramThroughOwnApp;
use App\Enums\PlatformAppKind;
use App\Models\PlatformApp;
use App\Models\Project;
use App\Services\Instagram\InstagramGraphApi;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Connects an Instagram account through the organization's own Meta app,
 * without Composio (Instagram API with Instagram Login). Same shape as
 * `LinkedinOAuthController`: the redirect is reached from inside a project,
 * the callback is a fixed `oauth/` URL, the state and the app travel in the
 * session.
 */
class InstagramOAuthController extends Controller
{
    public function redirect(Request $request, CurrentProject $currentProject, InstagramGraphApi $graph): RedirectResponse
    {
        $project = $currentProject->getOrFail();
        $app = PlatformApp::resolve($project->organization, PlatformAppKind::Instagram);

        if ($app === null) {
            return to_route('settings.instagram.index')->with('status', 'Add your Meta app first.');
        }

        $state = Str::random(40);
        $request->session()->put('instagram_oauth', ['state' => $state, 'project_id' => $project->id, 'platform_app_id' => $app->id]);

        return redirect()->away($graph->authorizationUrl($app, PlatformAppKind::Instagram->redirectUri(), $state));
    }

    public function callback(Request $request, InstagramGraphApi $graph, ConnectInstagramThroughOwnApp $connect): RedirectResponse
    {
        $session = (array) $request->session()->pull('instagram_oauth', []);
        $project = isset($session['project_id']) ? Project::query()->find((int) $session['project_id']) : null;

        if ($project === null || ! $request->user()->can('view', $project)) {
            return to_route('app.home')->with('status', 'Instagram connection failed: the request could not be verified.');
        }

        $app = PlatformApp::query()->where('platform', PlatformAppKind::Instagram)->find((int) ($session['platform_app_id'] ?? 0));

        if ($app === null || ! hash_equals((string) ($session['state'] ?? ''), (string) $request->query('state')) || ! $request->filled('code')) {
            return to_route('settings.instagram.index', ['project' => $project])->with('status', 'Instagram connection failed: the request could not be verified.');
        }

        try {
            $token = $graph->exchangeCode($app, (string) $request->query('code'), PlatformAppKind::Instagram->redirectUri());
            $message = $connect->handle($app, $project, $token);
        } catch (Throwable $e) {
            return to_route('settings.instagram.index', ['project' => $project])->with('status', "Instagram connection failed: {$e->getMessage()}");
        }

        return to_route('settings.instagram.index', ['project' => $project])->with('status', $message);
    }
}
