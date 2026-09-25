<?php

namespace App\Http\Controllers\AppSettings;

use App\Enums\SocialPostExampleSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\AppSettings\SocialPostExampleRequest;
use App\Http\Resources\AppSettings\SocialPostExampleResource;
use App\Models\SocialPostExample;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The shared banks of proven X and Bluesky posts, one per network, and the
 * like count a Bluesky post has to reach to join its bank on its own (see
 * `FetchSocialPostStats`). Mirrors `LinkedinPostExampleController`.
 */
class SocialPostExampleController extends Controller
{
    public function __construct(private Settings $settings) {}

    public function index(): Response
    {
        return Inertia::render('app-settings/SocialPostExamples', [
            'examples' => SocialPostExampleResource::collection(
                SocialPostExample::query()->with('addedBy')->latest('id')->get()
            ),
            'threshold' => [
                'min_likes' => $this->settings->int('social_examples.min_likes'),
            ],
        ]);
    }

    public function store(SocialPostExampleRequest $request): RedirectResponse
    {
        SocialPostExample::create([
            'platform' => $request->validated('platform'),
            'body' => $request->validated('body'),
            'source' => SocialPostExampleSource::Manual,
            'added_by_user_id' => $request->user()->id,
        ]);

        return to_route('app-settings.social-post-examples.index')->with('status', 'Example added.');
    }

    public function destroy(SocialPostExample $socialPostExample): RedirectResponse
    {
        $socialPostExample->delete();

        return to_route('app-settings.social-post-examples.index')->with('status', 'Example removed.');
    }
}
