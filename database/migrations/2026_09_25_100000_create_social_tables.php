<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Short posts for X and Bluesky: one writer, one queue, a `platform` column
 * rather than a table per network. LinkedIn keeps its own tables: its two
 * OAuth apps, named/anonymized pairs and member URNs are machinery neither
 * of these has.
 *
 * X has no account row at all: its API is paid per call, so the user posts
 * by hand and pastes the post URL back, same shape as Reddit and articles.
 * Bluesky publishes through its own free API with an app password.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // bluesky.
            $table->string('platform');
            // The handle the user signs in with, e.g. someone.bsky.social.
            $table->string('handle');
            // The account's permanent id on its network: a DID on Bluesky,
            // which survives a handle change.
            $table->string('external_id');
            $table->string('display_name');

            // A Bluesky app password, never the account password: revocable
            // from the user's own settings and unable to change the account.
            // Encrypted with CREDENTIALS_KEY via `EncryptedCredential`.
            $table->text('secret');

            // active|error.
            $table->string('status')->default('active');
            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->unique(['organization_id', 'platform', 'external_id']);
        });

        Schema::create('project_social_account', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['social_account_id', 'project_id']);
        });

        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            // x|bluesky.
            $table->string('platform');
            // Null on X, which publishes by hand with no account.
            $table->foreignId('social_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_run_id')->nullable()->constrained()->nullOnDelete();

            // knowledge_base|client_won|news|article|manual.
            $table->string('source_type');
            // The company id for client_won, the article id for article,
            // the news URL for news. Same role as `linkedin_posts.source_ref`.
            $table->string('source_ref')->nullable();

            // Shown beside the draft, same discipline as
            // `linkedin_posts.evidence`: never empty or generic.
            $table->text('evidence');
            $table->text('body');

            // draft|published|rejected. A failed Bluesky publish stays
            // `draft` with `last_error` set, same as LinkedIn.
            $table->string('status')->default('draft');
            $table->text('rejection_reason')->nullable();
            // The post's id on its network: an at:// URI on Bluesky, the
            // status id on X.
            $table->string('external_id')->nullable();
            $table->string('url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->text('last_error')->nullable();

            // Set by a user's "mark as successful" click OR by
            // `FetchSocialPostStats` crossing the like threshold. Either way,
            // only this project's own writer reads it.
            $table->timestamp('promoted_at')->nullable();
            $table->unsignedInteger('likes_count')->default(0);
            $table->timestamp('stats_checked_at')->nullable();

            $table->timestamps();

            $table->index(['project_id', 'platform', 'status']);
            $table->index(['project_id', 'source_type', 'source_ref']);
        });

        Schema::table('projects', function (Blueprint $table) {
            // off|daily|weekly|biweekly|monthly, default off: same opt-in
            // reasoning as `linkedin_post_frequency`.
            $table->string('x_post_frequency')->default('off');
            $table->timestamp('x_next_post_at')->nullable();
            $table->string('bluesky_post_frequency')->default('off');
            $table->timestamp('bluesky_next_post_at')->nullable();

            // supervised|autonomous, starting supervised: nothing may start
            // publishing under someone's name because a deploy happened. X
            // has no column, it never publishes on its own.
            $table->string('bluesky_autonomy_level')->default('supervised');

            // One tone box for both networks, since one writer serves both.
            $table->text('social_prompt_instructions')->nullable();
        });

        // A short post is still generative writing that judges between
        // several signals, so it ships on the same tier as the LinkedIn one.
        DB::table('settings')->updateOrInsert(
            ['key' => 'agents.social-post-writer'],
            [
                'value' => json_encode(['provider' => 'anthropic', 'model' => 'claude-opus-5', 'timeout' => 300]),
                'is_encrypted' => false,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        // The like count a Bluesky post needs before this project's own
        // writer treats it as a proven example.
        DB::table('settings')->updateOrInsert(
            ['key' => 'social_examples.min_likes'],
            ['value' => json_encode(10), 'is_encrypted' => false, 'updated_at' => now(), 'created_at' => now()],
        );
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', ['agents.social-post-writer', 'social_examples.min_likes'])->delete();

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'x_post_frequency', 'x_next_post_at',
                'bluesky_post_frequency', 'bluesky_next_post_at',
                'bluesky_autonomy_level', 'social_prompt_instructions',
            ]);
        });

        Schema::dropIfExists('social_posts');
        Schema::dropIfExists('project_social_account');
        Schema::dropIfExists('social_accounts');
    }
};
