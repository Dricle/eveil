<?php

namespace App\Http\Controllers;

use App\Actions\MarkRedditReplyPosted;
use App\Actions\SubmitManualRedditReply;
use App\Enums\RedditReplyStatus;
use App\Http\Requests\RedditReplyApproveRequest;
use App\Http\Requests\RedditReplyDestroyThreadRequest;
use App\Http\Requests\RedditReplyManualRequest;
use App\Http\Requests\RedditReplyRejectRequest;
use App\Http\Resources\RedditReplyResource;
use App\Jobs\ScanRedditOpportunities;
use App\Models\RedditReply;
use App\Support\CurrentProject;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The queue every drafted reply lands in - one page, since there is no
 * account to manage separately. Nothing posts on its own: the user copies
 * the body, posts it on reddit.com themselves, and comes back to mark it
 * posted. `RedditReply` is project-scoped (`BelongsToProject`), so per
 * `.ai/rules/controllers.md` it is never route-model-bound: `SubstituteBindings`
 * runs before `project.set`, so a bound model would be fetched with the
 * scope not yet applied. Take the id and look it up here instead.
 *
 * Every action on one row answers with `back()`, not this page: the same
 * rows are reviewed from the dashboard's to-review modal too, and a redirect
 * here would pull the user off the dashboard mid-review.
 */
class RedditReplyController extends Controller
{
    public function index(CurrentProject $currentProject): Response
    {
        $project = $currentProject->getOrFail();

        return Inertia::render('reddit/Replies', [
            'replies' => RedditReplyResource::collection(RedditReply::query()->latest()->get()),
            'scanFrequency' => $project->reddit_scan_frequency->value,
        ]);
    }

    public function scan(CurrentProject $currentProject): RedirectResponse
    {
        ScanRedditOpportunities::dispatch($currentProject->getOrFail());

        return to_route('reddit.replies.index')->with('status', 'Scanning for opportunities. New drafts will appear here shortly.');
    }

    /**
     * "Mark as posted": the only publish step this feature has, since there
     * is no API to publish through. Rejects the sibling angles for the same
     * thread first - only one angle per thread can ever be posted.
     */
    public function approve(RedditReplyApproveRequest $request, MarkRedditReplyPosted $mark, int $redditReply): RedirectResponse
    {
        $reply = RedditReply::query()->findOrFail($redditReply);

        $reply->sibling()->update([
            'status' => RedditReplyStatus::Rejected,
            'rejection_reason' => 'Superseded by another angle.',
        ]);

        $mark->handle($reply, $request->validated('comment_permalink'));

        return back();
    }

    /**
     * "I wrote my own": the user skipped all three drafted angles and
     * posted their own reply instead. Creates a fourth, already-published
     * row rather than overwriting a draft - see `SubmitManualRedditReply`.
     */
    public function manual(RedditReplyManualRequest $request, SubmitManualRedditReply $submit, CurrentProject $currentProject): RedirectResponse
    {
        $submit->handle(
            $currentProject->getOrFail(),
            $request->validated('thread_permalink'),
            $request->validated('body'),
            $request->validated('comment_permalink'),
        );

        return back();
    }

    /**
     * Keeps the row, with an optional reason - same "reject vs delete"
     * distinction as `LinkedinPostController::reject()`.
     */
    public function reject(RedditReplyRejectRequest $request, int $redditReply): RedirectResponse
    {
        RedditReply::query()->findOrFail($redditReply)->update([
            'status' => RedditReplyStatus::Rejected,
            'rejection_reason' => $request->validated('reason'),
        ]);

        return back();
    }

    public function destroy(int $redditReply): RedirectResponse
    {
        RedditReply::query()->findOrFail($redditReply)->delete();

        return back();
    }

    /**
     * Deletes every drafted angle sharing a thread in one go - the thread
     * itself is sometimes already removed by moderators, so nothing in it
     * can ever be posted. Scoped to `Draft`: a published or rejected sibling
     * for the same thread is left alone, since it already has a real outcome
     * worth keeping.
     */
    public function destroyThread(RedditReplyDestroyThreadRequest $request): RedirectResponse
    {
        RedditReply::query()
            ->where('thread_permalink', $request->validated('thread_permalink'))
            ->where('status', RedditReplyStatus::Draft)
            ->delete();

        return back();
    }

    /**
     * Project-scoped only: stamps `promoted_at` so this project's own
     * future drafts treat it as a proven example. Never touches the shared
     * instance-wide pool - see `LinkedinPostController::promote()` for the
     * same reasoning.
     */
    public function promote(int $redditReply): RedirectResponse
    {
        $reply = RedditReply::query()->findOrFail($redditReply);

        if ($reply->status === RedditReplyStatus::Published && $reply->promoted_at === null) {
            $reply->update(['promoted_at' => now()]);
        }

        return back();
    }
}
