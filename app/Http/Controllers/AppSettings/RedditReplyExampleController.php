<?php

namespace App\Http\Controllers\AppSettings;

use App\Enums\RedditReplyExampleSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\AppSettings\RedditReplyExampleRequest;
use App\Http\Resources\AppSettings\RedditReplyExampleResource;
use App\Models\RedditReplyExample;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The shared bank of proven Reddit replies, and the two score bars that let
 * something join it on its own: one for our own published replies
 * (`App\Actions\FetchRedditReplyStats`), one for a stranger's product
 * recommendation off a thread the scan read
 * (`App\Actions\HarvestRedditReplyExamples`). This screen manages the bank
 * and those settings, never promotes or harvests anything itself. Mirrors
 * `SocialPostExampleController` exactly.
 */
class RedditReplyExampleController extends Controller
{
    public function __construct(private Settings $settings) {}

    public function index(): Response
    {
        return Inertia::render('app-settings/RedditReplyExamples', [
            'examples' => RedditReplyExampleResource::collection(
                RedditReplyExample::query()->latest('id')->get()
            ),
            'threshold' => [
                'min_score' => $this->settings->int('reddit_examples.min_score'),
            ],
            'harvest' => [
                'enabled' => $this->settings->bool('reddit_examples.harvest_enabled'),
                'min_score' => $this->settings->int('reddit_examples.harvest_min_score'),
            ],
        ]);
    }

    public function store(RedditReplyExampleRequest $request): RedirectResponse
    {
        RedditReplyExample::create([
            'body' => $request->string('body')->value(),
            'source' => RedditReplyExampleSource::Manual,
            'added_by_user_id' => $request->user()->id,
        ]);

        return to_route('app-settings.reddit-reply-examples.index')->with('status', 'Example added.');
    }

    public function destroy(RedditReplyExample $redditReplyExample): RedirectResponse
    {
        $redditReplyExample->delete();

        return to_route('app-settings.reddit-reply-examples.index')->with('status', 'Example removed.');
    }
}
