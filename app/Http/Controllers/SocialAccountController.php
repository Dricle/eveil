<?php

namespace App\Http\Controllers;

use App\Enums\SocialAccountStatus;
use App\Enums\SocialPlatform;
use App\Http\Requests\SocialAccountProjectsRequest;
use App\Http\Requests\SocialAccountRequest;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\SocialAccountResource;
use App\Models\SocialAccount;
use App\Services\Bluesky\BlueskyClient;
use App\Services\Bluesky\SignInRefused;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Connected accounts. `index`/`store` are Bluesky's own screen, connected
 * with an app password: connecting the same account again replaces its
 * password, which is also how a revoked one is fixed. `update`/`destroy`
 * serve LinkedIn and Bluesky alike: granting an account to projects and
 * disconnecting it work the same whatever the network.
 */
class SocialAccountController extends Controller
{
    public function __construct(private CurrentProject $currentProject) {}

    public function index(): Response
    {
        $organization = $this->currentProject->organization();

        return Inertia::render('settings/Bluesky', [
            'accounts' => SocialAccountResource::collection(
                $organization->socialAccounts()->where('platform', SocialPlatform::Bluesky)->with('projects')->orderBy('id')->get()
            ),
            'projects' => ProjectResource::collection(
                $organization->projects()->orderBy('name')->get()->each->setRelation('organization', $organization)
            ),
        ]);
    }

    public function store(SocialAccountRequest $request, BlueskyClient $client): RedirectResponse
    {
        try {
            $profile = $client->connect($request->validated('handle'), $request->validated('app_password'));
        } catch (SignInRefused $e) {
            return back()->withErrors(['app_password' => $e->getMessage()]);
        }

        $organization = $this->currentProject->organization();

        $account = SocialAccount::query()->updateOrCreate(
            ['organization_id' => $organization->id, 'platform' => SocialPlatform::Bluesky, 'external_id' => $profile['did']],
            [
                'handle' => $profile['handle'],
                'display_name' => $profile['display_name'],
                'secret' => $request->validated('app_password'),
                'status' => SocialAccountStatus::Active,
                'last_error' => null,
            ],
        );

        // The project it was connected from can post through it straight
        // away: connecting and then granting would be two steps for one.
        $account->projects()->syncWithoutDetaching([$this->currentProject->getOrFail()->id]);

        return to_route('settings.bluesky.index')->with('status', "Connected {$account->handle}.");
    }

    public function update(SocialAccountProjectsRequest $request, int $socialAccount): RedirectResponse
    {
        $account = SocialAccount::query()->ownedBy($request->user())->findOrFail($socialAccount);

        $account->projects()->sync($request->validated('projects', []));

        return back();
    }

    public function destroy(Request $request, int $socialAccount): RedirectResponse
    {
        SocialAccount::query()->ownedBy($request->user())->findOrFail($socialAccount)->delete();

        return back();
    }
}
