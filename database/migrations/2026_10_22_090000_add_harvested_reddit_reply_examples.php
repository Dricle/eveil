<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A third source for `reddit_reply_examples`: a comment somebody else wrote,
 * kept because it scored well on a thread the scan already read and because
 * it recommends a product. The two existing sources are ours (a superadmin's
 * own text, or one of our published replies that crossed its score floor);
 * this one is not, so every harvested row keeps the link back to the comment
 * it came from - who wrote it, where, and what it scored - and the writer's
 * prompt shows it in its own labelled section rather than mixed in with
 * ours.
 *
 * The columns are nullable because a manual or promoted row has none of
 * them: there is no Reddit comment behind it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reddit_reply_examples', function (Blueprint $table) {
            // Reddit's own base-36 comment id, the only stable handle on a
            // comment. The harvest skips a comment without one rather than
            // storing a row it could never dedupe against.
            $table->string('reddit_comment_id')->nullable()->after('source');
            $table->string('comment_permalink')->nullable()->after('reddit_comment_id');
            $table->string('comment_author')->nullable()->after('comment_permalink');
            $table->string('subreddit')->nullable()->after('comment_author');
            $table->unsignedInteger('score')->nullable()->after('subreddit');

            // One sentence from the triage on why this one is worth learning
            // from, shown on the settings screen so a superadmin auditing the
            // bank can see the judgment rather than re-make it.
            $table->text('harvest_reason')->nullable()->after('score');
        });

        // Same partial-index trick as `reddit_reply_examples_reddit_reply_id_unique`
        // and for the same reason: Blueprint's `unique()` has no WHERE, and
        // every manual or promoted row leaves this column null.
        DB::statement('
            create unique index reddit_reply_examples_reddit_comment_id_unique
            on reddit_reply_examples (reddit_comment_id)
            where reddit_comment_id is not null
        ');

        DB::table('settings')->updateOrInsert(
            ['key' => 'reddit_examples.harvest_enabled'],
            ['value' => json_encode(true), 'is_encrypted' => false, 'updated_at' => now(), 'created_at' => now()],
        );

        // Deliberately well above `reddit_examples.min_score` (10, the bar one
        // of our own replies clears): that one is measured on a comment we
        // wrote and already believe in, this one is the only filter standing
        // between a stranger's comment and every project's prompt. Measured
        // against a real thread: a 406-point r/selfhosted post's top comments
        // ran 34, 31, 21, 11, 9, so 25 keeps the top two.
        DB::table('settings')->updateOrInsert(
            ['key' => 'reddit_examples.harvest_min_score'],
            ['value' => json_encode(25), 'is_encrypted' => false, 'updated_at' => now(), 'created_at' => now()],
        );

        DB::table('settings')->updateOrInsert(
            ['key' => 'agents.reddit-example-triage'],
            [
                'value' => json_encode(['provider' => 'anthropic', 'model' => 'claude-haiku-4-5', 'timeout' => 60]),
                'is_encrypted' => false,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'reddit_examples.harvest_enabled',
            'reddit_examples.harvest_min_score',
            'agents.reddit-example-triage',
        ])->delete();

        DB::statement('drop index if exists reddit_reply_examples_reddit_comment_id_unique');

        Schema::table('reddit_reply_examples', function (Blueprint $table) {
            $table->dropColumn([
                'reddit_comment_id',
                'comment_permalink',
                'comment_author',
                'subreddit',
                'score',
                'harvest_reason',
            ]);
        });
    }
};
