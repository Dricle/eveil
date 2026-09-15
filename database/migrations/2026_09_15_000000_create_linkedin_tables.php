<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Personal-profile LinkedIn posting: OAuth-connected accounts, owned by the
 * ORGANIZATION and granted to projects, same shape as `email_accounts`
 * (`.ai/rules/outreach.md`'s "the organization owns the mailbox, the project
 * is granted it") - one LinkedIn identity is often used across several
 * products and never for a third.
 *
 * `linkedin_posts` is project-scoped instead: the content itself belongs to
 * one product's voice, not to the organization.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('linkedin_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->string('member_urn');
            $table->string('display_name');

            // OAuth tokens, never a password: encrypted with CREDENTIALS_KEY
            // via the model's `EncryptedCredential` cast, never APP_KEY.
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->timestamp('refresh_token_expires_at')->nullable();

            // active|expired|error, plain string per the tenancy migration's
            // own rule: a DB enum() needs a drop-and-recreate for every new
            // value on Postgres.
            $table->string('status')->default('active');
            $table->text('last_error')->nullable();
            $table->timestamp('last_checked_at')->nullable();

            $table->timestamps();
        });

        Schema::create('linkedin_account_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('linkedin_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['linkedin_account_id', 'project_id']);
        });

        Schema::create('linkedin_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('linkedin_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_run_id')->nullable()->constrained()->nullOnDelete();

            // knowledge_base|client_won|news|manual.
            $table->string('source_type');
            // The company id for client_won, the article URL for news, null
            // for knowledge_base/manual.
            $table->string('source_ref')->nullable();
            // named|anonymized on a client_won sibling pair, null everywhere
            // else: only one of a pair can ever be published.
            $table->string('variant')->nullable();

            // Shown beside the draft in the approval queue, same discipline
            // as `company_target_evaluations.fit_reason`: never empty or
            // generic.
            $table->text('evidence');
            $table->text('body');

            // draft|approved|published|rejected|failed.
            $table->string('status')->default('draft');
            $table->string('urn')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->index(['project_id', 'status']);
            // What `GenerateDueLinkedinPosts` checks before treating a Won
            // company as unconsumed: has this company already produced a post.
            $table->index(['project_id', 'source_type', 'source_ref']);
        });

        Schema::table('projects', function (Blueprint $table) {
            // off|daily|weekly|biweekly|monthly, default off: nothing is
            // drafted until the user opts in, same as a project starting
            // with no mailbox attached.
            $table->string('linkedin_post_frequency')->default('off')->after('autonomy_level');
            $table->timestamp('linkedin_next_post_at')->nullable()->after('linkedin_post_frequency');
        });

        // The writer is generative (drafts a full post, judges relevance
        // across several signals in one call) so it ships on the same tier
        // as WebsiteAnalyst/SequenceWriter: Opus, 300s. Seeded here rather
        // than in the original `seed_default_settings` migration, same
        // pattern as `add_sequence_writing` adding its own agents later.
        DB::table('settings')->updateOrInsert(
            ['key' => 'agents.linkedin-post-writer'],
            [
                'value' => json_encode(['provider' => 'anthropic', 'model' => 'claude-opus-5', 'timeout' => 300]),
                'is_encrypted' => false,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'agents.linkedin-post-writer')->delete();

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['linkedin_post_frequency', 'linkedin_next_post_at']);
        });

        Schema::dropIfExists('linkedin_posts');
        Schema::dropIfExists('linkedin_account_project');
        Schema::dropIfExists('linkedin_accounts');
    }
};
