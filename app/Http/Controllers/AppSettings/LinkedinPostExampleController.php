<?php

namespace App\Http\Controllers\AppSettings;

use App\Enums\LinkedinPostExampleSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\AppSettings\LinkedinPostExampleRequest;
use App\Http\Resources\AppSettings\LinkedinPostExampleResource;
use App\Models\LinkedinPostExample;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The shared bank of proven LinkedIn posts, and the like-count bar a post's
 * own performance has to clear to join it automatically - see
 * `App\Actions\FetchLinkedinPostStats`, the thing that actually promotes one.
 * This screen only manages the bank and its threshold, never promotes
 * anything itself. Mirrors `EmailExampleController` exactly.
 */
class LinkedinPostExampleController extends Controller
{
    public function __construct(private Settings $settings) {}

    public function index(): Response
    {
        return Inertia::render('app-settings/LinkedinPostExamples', [
            'examples' => LinkedinPostExampleResource::collection(
                LinkedinPostExample::query()->latest('id')->get()
            ),
            'threshold' => [
                'min_likes' => $this->settings->int('linkedin_examples.min_likes'),
            ],
        ]);
    }

    public function store(LinkedinPostExampleRequest $request): RedirectResponse
    {
        LinkedinPostExample::create([
            'body' => $request->string('body')->value(),
            'source' => LinkedinPostExampleSource::Manual,
            'added_by_user_id' => $request->user()->id,
        ]);

        return to_route('app-settings.linkedin-post-examples.index')->with('status', 'Example added.');
    }

    public function destroy(LinkedinPostExample $linkedinPostExample): RedirectResponse
    {
        $linkedinPostExample->delete();

        return to_route('app-settings.linkedin-post-examples.index')->with('status', 'Example removed.');
    }
}
