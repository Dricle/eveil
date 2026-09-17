<?php

namespace App\Http\Controllers;

use App\Actions\PublishLinkedinPost;
use App\Enums\LinkedinPostStatus;
use App\Http\Requests\LinkedinPostApproveRequest;
use App\Http\Requests\LinkedinPostRejectRequest;
use App\Http\Requests\LinkedinPostRequest;
use App\Http\Resources\LinkedinAccountResource;
use App\Http\Resources\LinkedinPostResource;
use App\Models\LinkedinAccount;
use App\Models\LinkedinPost;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The approval queue every source (knowledge base, client win, news, manual
 * via Evie) lands in. Nothing publishes without a human approving here,
 * whatever the project's autonomy level says.
 *
 * `LinkedinPost` is project-scoped (`BelongsToProject`), so per
 * `.ai/rules/controllers.md` it is never route-model-bound: `SubstituteBindings`
 * runs before `project.set`, so a bound model would be fetched with the scope
 * not yet applied. Take the id and look it up here instead.
 */
class LinkedinPostController extends Controller
{
    public function index(CurrentProject $currentProject): Response
    {
        $accounts = $currentProject->getOrFail()->linkedinAccounts()->get();

        return Inertia::render('linkedin/Posts', [
            'posts' => LinkedinPostResource::collection(
                LinkedinPost::query()->with('linkedinAccount')->latest()->get()
            ),
            'linkedinAccounts' => LinkedinAccountResource::collection($accounts),
            'hasAccount' => $accounts->isNotEmpty(),
            'currentProjectFrequency' => $currentProject->getOrFail()->linkedin_post_frequency->value,
        ]);
    }

    public function update(LinkedinPostRequest $request, int $linkedinPost): RedirectResponse
    {
        LinkedinPost::query()->findOrFail($linkedinPost)->update($request->validated());

        return to_route('linkedin.posts.index');
    }

    /**
     * Approving and publishing are the same action: there is no separate
     * `approved` status to sit in first. A failure leaves the row at
     * `Draft` with `last_error` set, so the exact same button remains the
     * retry.
     *
     * Which account it goes to is now explicit (`linkedin_account_id` on the
     * request), not the first one attached to the project:
     * `LinkedinPostApproveRequest` confirms it is one the project is
     * actually granted before this ever touches `PublishLinkedinPost`.
     */
    public function approve(LinkedinPostApproveRequest $request, PublishLinkedinPost $publish, int $linkedinPost): RedirectResponse
    {
        $post = LinkedinPost::query()->findOrFail($linkedinPost);
        $account = LinkedinAccount::query()->findOrFail((int) $request->validated('linkedin_account_id'));

        $post->update(['linkedin_account_id' => $account->id]);

        // Only one of a named/anonymized pair can ever go out: the user just
        // chose which by approving this one.
        $post->sibling()->update([
            'status' => LinkedinPostStatus::Rejected,
            'rejection_reason' => 'Superseded by the other variant.',
        ]);

        $publish->handle($post, $account);

        return to_route('linkedin.posts.index');
    }

    /**
     * Keeps the row, with an optional reason: `GenerateLinkedinPost::prompt()`
     * reads recent rejections (and why) so the writer stops reproducing them.
     */
    public function reject(LinkedinPostRejectRequest $request, int $linkedinPost): RedirectResponse
    {
        LinkedinPost::query()->findOrFail($linkedinPost)->update([
            'status' => LinkedinPostStatus::Rejected,
            'rejection_reason' => $request->validated('reason'),
        ]);

        return to_route('linkedin.posts.index');
    }

    /**
     * Hard delete: no trace, no feedback. Different from reject on purpose -
     * see `reject()`.
     */
    public function destroy(int $linkedinPost): RedirectResponse
    {
        LinkedinPost::query()->findOrFail($linkedinPost)->delete();

        return to_route('linkedin.posts.index');
    }

    /**
     * Project-scoped only: stamps `promoted_at` so THIS project's own
     * `GenerateLinkedinPost::prompt()` treats it as a proven example. Never
     * touches the shared instance-wide `linkedin_post_examples` pool - a
     * user's own click can only ever affect their own project's future
     * drafts, which is what makes it safe with no review step. The shared
     * pool is fed only by a superadmin's own hand or `FetchLinkedinPostStats`'
     * real, externally-measured number.
     */
    public function promote(int $linkedinPost): RedirectResponse
    {
        $post = LinkedinPost::query()->findOrFail($linkedinPost);

        if ($post->status === LinkedinPostStatus::Published && $post->promoted_at === null) {
            $post->update(['promoted_at' => now()]);
        }

        return to_route('linkedin.posts.index');
    }
}
