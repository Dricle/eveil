<?php

namespace App\Http\Controllers;

use App\Actions\MarkSocialPostPublished;
use App\Actions\PublishSocialPost;
use App\Enums\SocialPlatform;
use App\Enums\SocialPostStatus;
use App\Http\Requests\SocialPostApproveRequest;
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
 * One queue per network, each its own nav entry, all on the same rows. A
 * LinkedIn or Bluesky draft is approved and published through the network's
 * API (`approve()`); an X draft is copied, posted by hand, and its URL
 * pasted back (`publish()`).
 *
 * `SocialPost` is project-scoped, so per `.ai/rules/controllers.md` it is
 * never route-model-bound. Every action on one row answers with `back()`:
 * the same rows are reviewed from the dashboard's to-review modal too.
 */
class SocialPostController extends Controller
{
    public function index(CurrentProject $currentProject, SocialPlatform $platform): Response
    {
        $project = $currentProject->getOrFail();

        return Inertia::render('social/Posts', [
            'platform' => $platform->value,
            'posts' => SocialPostResource::collection(
                SocialPost::query()->where('platform', $platform)->with('socialAccount')->latest()->get()
            ),
            'accounts' => SocialAccountResource::collection(
                $project->socialAccounts()->where('platform', $platform)->get()
            ),
            'frequency' => $project->getAttribute($platform->frequencyColumn())->value,
        ]);
    }

    public function generate(CurrentProject $currentProject, SocialPlatform $platform): RedirectResponse
    {
        GenerateSocialPost::dispatch($currentProject->getOrFail(), $platform);

        return to_route('social.posts.index', $platform)->with('status', "Writing a {$platform->label()} post. It will appear here shortly.");
    }

    public function update(SocialPostRequest $request, int $socialPost): RedirectResponse
    {
        SocialPost::query()->where('status', SocialPostStatus::Draft)->findOrFail($socialPost)->update($request->validated());

        return back();
    }

    /**
     * LinkedIn and Bluesky: approving and publishing are the same action,
     * there is no `approved` status to sit in first. A failure leaves the
     * draft with `last_error`, so the same button retries. Only one of a
     * named/anonymized pair can ever go out: the user just chose which.
     */
    public function approve(SocialPostApproveRequest $request, PublishSocialPost $publish, int $socialPost): RedirectResponse
    {
        $post = SocialPost::query()
            ->where('platform', '!=', SocialPlatform::X)
            ->where('status', SocialPostStatus::Draft)
            ->findOrFail($socialPost);

        $account = SocialAccount::query()->where('platform', $post->platform)->findOrFail((int) $request->validated('social_account_id'));

        $post->sibling()->update([
            'status' => SocialPostStatus::Rejected,
            'rejection_reason' => 'Superseded by the other variant.',
        ]);

        $publish->handle($post, $account);

        return back();
    }

    /**
     * X only: "I posted it", with the post's URL. Rejects a client win's
     * other variant, same as approving does.
     */
    public function publish(SocialPostPublishRequest $request, MarkSocialPostPublished $mark, int $socialPost): RedirectResponse
    {
        $post = SocialPost::query()
            ->where('platform', SocialPlatform::X)
            ->where('status', SocialPostStatus::Draft)
            ->findOrFail($socialPost);

        $post->sibling()->update([
            'status' => SocialPostStatus::Rejected,
            'rejection_reason' => 'Superseded by the other variant.',
        ]);

        $mark->handle($post, $request->validated('url'));

        return back();
    }

    /**
     * Keeps the row, with an optional reason the writer reads next time.
     * Delete below is the hard removal: two different actions on purpose.
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
     * proven example. Never touches the shared instance-wide bank: a user's
     * own click can only ever affect their own project, which is what makes
     * it safe with no review step. The bank is fed only by a superadmin's
     * hand or `FetchSocialPostStats`' real, externally-measured number.
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
