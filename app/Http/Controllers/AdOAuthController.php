<?php

namespace App\Http\Controllers;

use App\Actions\ConnectAdAccounts;
use App\Enums\AdPlatform;
use App\Models\PlatformApp;
use App\Models\Project;
use App\Services\Ads\AdPlatformException;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Connects ad accounts through the network's OAuth, for any network: the
 * driver builds the URLs and trades the code, this only carries the state.
 * The callback is a fixed URL outside `{project:slug}` (see
 * `.ai/rules/controllers-models.md`), so the project travels in the session.
 */
class AdOAuthController extends Controller
{
    public function redirect(Request $request, AdPlatform $platform, CurrentProject $currentProject): RedirectResponse
    {
        $project = $currentProject->getOrFail();
        $app = PlatformApp::resolve($project->organization, $platform);

        if ($app === null) {
            return to_route('settings.ad-platforms.index')->withErrors(['platform' => "Add a {$platform->label()} app first."]);
        }

        $state = Str::random(40);
        $request->session()->put('ads_oauth', ['state' => $state, 'project_id' => $project->id, 'platform_app_id' => $app->id]);

        return redirect()->away($platform->client()->authorizationUrl($app, route('oauth.ads.callback', $platform), $state));
    }

    public function callback(Request $request, AdPlatform $platform, ConnectAdAccounts $connect): RedirectResponse
    {
        $session = (array) $request->session()->pull('ads_oauth', []);
        $project = isset($session['project_id']) ? Project::query()->find((int) $session['project_id']) : null;

        if ($project === null || ! $request->user()->can('view', $project)) {
            return to_route('app.home')->with('status', 'Connection failed: the request could not be verified.');
        }

        $app = PlatformApp::query()->where('platform', $platform)->find((int) ($session['platform_app_id'] ?? 0));

        if ($app === null || ! hash_equals((string) ($session['state'] ?? ''), (string) $request->query('state')) || ! $request->filled('code')) {
            return to_route('ads.index', ['project' => $project])->with('status', "{$platform->label()} connection failed: the request could not be verified.");
        }

        try {
            $tokens = $platform->client()->exchangeCode($app, (string) $request->query('code'), route('oauth.ads.callback', $platform));
            $count = $connect->handle($project, $platform, $app, $tokens);
        } catch (AdPlatformException $e) {
            return to_route('ads.index', ['project' => $project])->with('status', $e->getMessage());
        }

        return to_route('ads.index', ['project' => $project])->with('status', $count === 0
            ? "Connected, but {$platform->label()} lists no ad account you can manage."
            : "Connected {$count} {$platform->label()} account(s). Set a monthly budget to let Eveil spend.");
    }
}
