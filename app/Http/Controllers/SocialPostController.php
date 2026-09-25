<?php

namespace App\Http\Controllers;

use App\Actions\MarkSocialPostPublished;
use App\Actions\PublishSocialPost;
use App\Enums\SocialPlatform;
use App\Enums\SocialPostStatus;
use App\Http\Requests\SocialPostApproveRequest;
use App\Http\Requests\SocialPostGenerateRequest;
use App\Http\Requests\SocialPostPublishRequest;
use App\Http\Requests\SocialPostRejectRequest;
use App\Http\Requests\SocialPostRequest;
use App\Http\Resources\SocialAccountResource;
use App\Http\Resources\SocialPostResource;
use App\Jobs\GenerateSocialPost;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The queue every X and Bluesky draft lands in. The two publish differently:
 * a Bluesky draft is approved and published through Bluesky's API, an X draft
 * is copied, posted by hand, and its URL pasted back (`publish()`).
 *
 * `SocialPost` is project-scoped, so per `.ai/rules/controllers.md` it is
 * never route-model-bound. Every action on one row answers with `back()`:
 * the same rows are reviewed from the dashboard's to-review modal too.
 */
class SocialPostController extends Controller
{
    public function index(CurrentProject $currentProject): Response
    {
        $project = $currentProject->getOrFail();

        return Inertia::render('social/Posts', [
            'posts' => SocialPostResource::collection(SocialPost::query()->with('socialAccount')->latest()->get()),
            'blueskyAccounts' => SocialAccountResource::collection(
                $project->socialAccounts()->where('platform', SocialPlatform::Bluesky)->get()
            ),
            'frequencies' => [
                'x' => $project->x_post_frequency->value,
                'bluesky' => $project->bluesky_post_frequency->value,
            ],
        ]);
    }

    public function generate(SocialPostGenerateRequest $request, CurrentProject $currentProject): RedirectResponse
    {
        $platform = SocialPlatform::from($request->validated('platform'));

        GenerateSocialPost::dispatch($currentProject->getOrFail(), $platform);

        return to_route('social.posts.index')->with('status', "Writing a {$platform->label()} post. It will appear here shortly.");
    }

    public function update(SocialPostRequest $request, int $socialPost): RedirectResponse
    {
        SocialPost::query()->where('status', SocialPostStatus::Draft)->findOrFail($socialPost)->update($request->validated());

        return back();
    }

    /**
     * Bluesky only: approving and publishing are the same action, as on
     * LinkedIn. A failure leaves the draft with `last_error`, so the same
     * button retries.
     */
    public function approve(SocialPostApproveRequest $request, PublishSocialPost $publish, int $socialPost): RedirectResponse
    {
        $post = SocialPost::query()
            ->where('platform', SocialPlatform::Bluesky)
            ->where('status', SocialPostStatus::Draft)
            ->findOrFail($socialPost);

        $publish->handle($post, SocialAccount::query()->findOrFail((int) $request->validated('social_account_id')));

        return back();
    }

    /**
     * X only: "I posted it", with the post's URL.
     */
    public function publish(SocialPostPublishRequest $request, MarkSocialPostPublished $mark, int $socialPost): RedirectResponse
    {
        $post = SocialPost::query()
            ->where('platform', SocialPlatform::X)
            ->where('status', SocialPostStatus::Draft)
            ->findOrFail($socialPost);

        $mark->handle($post, $request->validated('url'));

        return back();
    }

    /**
     * Keeps the row, with an optional reason the writer reads next time.
     */
    public function reject(SocialPostRejectRequest $request, int $socialPost): RedirectResponse
    {
        SocialPost::query()->findOrFail($socialPost)->update([
            'status' => SocialPostStatus::Rejected,
            'rejection_reason' => $request->validated('reason'),
        ]);

        return back();
    }

    public function destroy(int $socialPost): RedirectResponse
    {
        SocialPost::query()->findOrFail($socialPost)->delete();

        return back();
    }

    /**
     * Stamps `promoted_at` so this project's own writer treats the post as a
     * proven example. The only way in for an X post, which Eveil never reads
     * numbers for.
     */
    public function promote(int $socialPost): RedirectResponse
    {
        $post = SocialPost::query()->findOrFail($socialPost);

        if ($post->status === SocialPostStatus::Published && $post->promoted_at === null) {
            $post->update(['promoted_at' => now()]);
        }

        return back();
    }
}
