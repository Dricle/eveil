<?php

namespace App\Actions;

use App\Enums\RedditReplyAngle;
use App\Enums\RedditReplyStatus;
use App\Models\Project;
use App\Models\RedditReply;

/**
 * The "I wrote my own" path: the user ignored all three drafted angles and
 * posted their own reply instead, but still wants Eveil tracking its score
 * and folding it into the writer's examples once it earns that
 * (`FetchRedditReplyStats`, same threshold as a drafted angle - see
 * `.ai/rules/controllers-actions.md`, nothing here bypasses it).
 *
 * Stored as a fourth angle rather than overwriting a drafted row, so the
 * three original drafts stay intact for comparison. Rejects them the same
 * way `RedditReplyController::approve()` rejects sibling angles: only one
 * angle per thread can ever be published.
 */
class SubmitManualRedditReply
{
    public function handle(Project $project, string $threadPermalink, string $body, string $commentPermalink): RedditReply
    {
        $thread = RedditReply::query()
            ->where('thread_permalink', $threadPermalink)
            ->firstOrFail();

        RedditReply::query()
            ->where('thread_permalink', $threadPermalink)
            ->where('status', RedditReplyStatus::Draft)
            ->update([
                'status' => RedditReplyStatus::Rejected,
                'rejection_reason' => 'Superseded by a manually written reply.',
            ]);

        return RedditReply::create([
            'project_id' => $project->id,
            'subreddit' => $thread->subreddit,
            'thread_permalink' => $threadPermalink,
            'thread_title' => $thread->thread_title,
            'source' => $thread->source,
            'search_query' => $thread->search_query,
            'angle' => RedditReplyAngle::UserWritten,
            'evidence' => $thread->evidence,
            'body' => $body,
            'status' => RedditReplyStatus::Published,
            'published_at' => now(),
            'comment_permalink' => $commentPermalink,
        ]);
    }
}
