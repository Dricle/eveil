<?php

namespace App\Http\Controllers\AppSettings;

use App\Enums\SocialPlatform;
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
 * The shared banks of proven posts, one per network, and the like count a
 * post has to reach to join its bank on its own (see `FetchSocialPostStats`,
 * the thing that actually promotes one). This screen only manages the banks
 * and their thresholds, never promotes anything itself.
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
            // Per network that reads its numbers; X has none.
            'thresholds' => collect(SocialPlatform::cases())
                ->filter(fn (SocialPlatform $platform): bool => $platform->minLikesSetting() !== null)
                ->mapWithKeys(fn (SocialPlatform $platform): array => [$platform->value => $this->settings->int((string) $platform->minLikesSetting())]),
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
