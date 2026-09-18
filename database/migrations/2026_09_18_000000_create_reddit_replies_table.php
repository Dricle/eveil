<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reddit engagement: no OAuth, no connected account - Reddit is currently
 * blocking new API app registration entirely, confirmed by the user trying.
 * Publishing is manual: the user copies a drafted reply, posts it on
 * reddit.com themselves, and comes back to mark it posted. Two independent
 * ways a thread gets found (`reddit_replies.source`) feed the exact same
 * drafting/approval pipeline - a live subreddit scan, and an evergreen
 * "best X"/"X alternative" thread that already ranks on Google.
 *
 * `reddit_reply_examples` is the same shape as `linkedin_post_examples`:
 * shared instance-wide, fed by exactly two trusted sources (a superadmin
 * typing one in, or `FetchRedditReplyStats` crossing a real score read
 * through FlareSolverr - see that action's docblock for why a plain
 * unauthenticated fetch does not work here).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reddit_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_run_id')->nullable()->constrained()->nullOnDelete();

            // Null for a seo_thread candidate whose subreddit
            // `SubredditFinder` never resolved - Arctic Shift's own record
            // of the thread is enough, the name is not load-bearing there.
            $table->string('subreddit')->nullable();
            $table->string('thread_permalink');
            $table->text('thread_title')->nullable();

            // subreddit_scan|seo_thread.
            $table->string('source');
            // Which buyer-intent query surfaced it, seo_thread only.
            $table->string('search_query')->nullable();

            // value_comment|soft_mention|dm_invite.
            $table->string('angle');

            // Shown beside the draft in the approval queue, same discipline
            // as `linkedin_posts.evidence`: never empty or generic.
            $table->text('evidence');
            $table->text('body');

            // draft|published|rejected. "Published" here means self-reported
            // (`MarkRedditReplyPosted`), not an API confirmation - there is
            // no API call in this feature at all.
            $table->string('status')->default('draft');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('published_at')->nullable();

            // Pasted back by the user after posting by hand - the only way
            // this app ever learns a comment's real Reddit id. Nullable and
            // fine to skip: it is what makes score polling possible for
            // this row at all, never required to mark it posted.
            $table->string('comment_permalink')->nullable();
            $table->unsignedInteger('score')->default(0);
            $table->timestamp('stats_checked_at')->nullable();

            // This project's own "mark as proven" click. Feeds only this
            // project's own future drafts - never the shared pool, which is
            // fed exclusively by `FetchRedditReplyStats` crossing a real,
            // externally-measured score, or a superadmin's own hand.
            $table->timestamp('promoted_at')->nullable();

            $table->timestamps();

            $table->index(['project_id', 'status']);
            // NOT unique: up to 3 rows (one per angle) share one permalink
            // by design - `ScanRedditOpportunities` drafts all 3 in one
            // pass. Dedupe against re-scanning the same thread happens in
            // application code (`OpportunityScanner`/`SeoThreadFinder`
            // reject a permalink already present here), not at this index.
            $table->index(['project_id', 'thread_permalink']);
        });

        Schema::create('reddit_reply_examples', function (Blueprint $table) {
            $table->id();
            $table->text('body');
            $table->string('source'); // manual|promoted

            $table->foreignId('reddit_reply_id')->nullable()->constrained('reddit_replies')->nullOnDelete();
            $table->foreignId('added_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });

        // The poll's idempotency guard, same `DB::statement` trick as
        // `linkedin_post_examples`: Blueprint's `unique()` has no WHERE, and
        // a manually-added row never sets `reddit_reply_id` at all.
        DB::statement('
            create unique index reddit_reply_examples_reddit_reply_id_unique
            on reddit_reply_examples (reddit_reply_id)
            where reddit_reply_id is not null
        ');

        Schema::table('projects', function (Blueprint $table) {
            // off|daily|weekly|biweekly|monthly, default off - same opt-in
            // reasoning as `linkedin_post_frequency`.
            $table->string('reddit_scan_frequency')->default('off')->after('linkedin_next_post_at');
            $table->timestamp('reddit_next_scan_at')->nullable()->after('reddit_scan_frequency');
        });

        DB::table('settings')->updateOrInsert(
            ['key' => 'agents.reddit-opportunity-triage'],
            [
                'value' => json_encode(['provider' => 'anthropic', 'model' => 'claude-haiku-4-5', 'timeout' => 60]),
                'is_encrypted' => false,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        DB::table('settings')->updateOrInsert(
            ['key' => 'agents.reddit-reply-writer'],
            [
                'value' => json_encode(['provider' => 'anthropic', 'model' => 'claude-opus-5', 'timeout' => 300]),
                'is_encrypted' => false,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        DB::table('settings')->updateOrInsert(
            ['key' => 'reddit_examples.min_score'],
            ['value' => json_encode(10), 'is_encrypted' => false, 'updated_at' => now(), 'created_at' => now()],
        );
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'agents.reddit-opportunity-triage',
            'agents.reddit-reply-writer',
            'reddit_examples.min_score',
        ])->delete();

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['reddit_scan_frequency', 'reddit_next_scan_at']);
        });

        Schema::dropIfExists('reddit_reply_examples');
        Schema::dropIfExists('reddit_replies');
    }
};
