<?php

namespace App\Http\Controllers;

use App\Enums\AdPlatform;
use App\Http\Requests\PlatformAppRequest;
use App\Models\PlatformApp;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * An organization's own OAuth app per ad network (bring your own). Optional
 * where the instance already provides one; it then wins over it. The
 * redirect URI is shown because it is registered by hand on the network's
 * console.
 */
class PlatformAppController extends Controller
{
    public function __construct(private CurrentProject $currentProject) {}

    public function index(): Response
    {
        $organization = $this->currentProject->organization();
        $own = $organization->platformApps()->get()->keyBy(fn (PlatformApp $app): string => $app->platform->value);
        $instance = PlatformApp::query()->whereNull('organization_id')->pluck('platform')->map(fn (AdPlatform $platform): string => $platform->value);

        return Inertia::render('settings/AdPlatforms', [
            'platforms' => array_map(fn (AdPlatform $platform): array => [
                'value' => $platform->value,
                'label' => $platform->label(),
                'client_id' => $own->get($platform->value)?->client_id,
                'has_own_app' => $own->has($platform->value),
                'has_instance_app' => $instance->contains($platform->value),
                'redirect_uri' => route('oauth.ads.callback', $platform),
            ], AdPlatform::cases()),
        ]);
    }

    public function update(PlatformAppRequest $request, AdPlatform $platform): RedirectResponse
    {
        $this->currentProject->organization()->platformApps()->updateOrCreate(
            ['platform' => $platform],
            $request->validated(),
        );

        return back()->with('status', "{$platform->label()} app saved.");
    }

    public function destroy(AdPlatform $platform): RedirectResponse
    {
        $this->currentProject->organization()->platformApps()->where('platform', $platform)->delete();

        return back()->with('status', "{$platform->label()} app removed.");
    }
}
