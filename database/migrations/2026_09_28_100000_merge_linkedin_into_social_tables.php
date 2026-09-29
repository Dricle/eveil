<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LinkedIn joins X and Bluesky on the `social_*` tables: one post model, one
 * writer, one driver interface, a `platform` column. Its rows are copied
 * across, ids remapped, and the `linkedin_*` tables dropped.
 *
 * Tokens are copied as stored, never decrypted: both sides use the same
 * `EncryptedCredential` cast on CREDENTIALS_KEY, so the ciphertext is valid
 * as it is. LinkedIn's second token pair (the separate performance-polling
 * app) gets its own `stats_*` columns.
 *
 * One-way: `down()` refuses rather than pretend to split the data back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            // LinkedIn has no handle, only a member URN (`external_id`).
            $table->string('handle')->nullable()->change();

            // `secret` is the app password on Bluesky and the access token on
            // LinkedIn. The rest only ever exists for an OAuth network.
            $table->text('refresh_secret')->nullable()->after('secret');
            $table->timestamp('secret_expires_at')->nullable()->after('refresh_secret');
            $table->timestamp('refresh_secret_expires_at')->nullable()->after('secret_expires_at');

            // LinkedIn's performance polling lives on a second developer app
            // with its own OAuth connection. Null for most accounts.
            $table->text('stats_secret')->nullable()->after('refresh_secret_expires_at');
            $table->text('stats_refresh_secret')->nullable()->after('stats_secret');
            $table->timestamp('stats_secret_expires_at')->nullable()->after('stats_refresh_secret');
            $table->timestamp('stats_refresh_secret_expires_at')->nullable()->after('stats_secret_expires_at');
        });

        Schema::table('social_posts', function (Blueprint $table) {
            // named|anonymized on a client_won sibling pair, null elsewhere:
            // only one of a pair can ever be published.
            $table->string('variant')->nullable()->after('source_ref');
        });

        Schema::table('projects', function (Blueprint $table) {
            // One tone box per network, `{platform}_prompt_instructions`,
            // like the cadence and autonomy columns. The shared X/Bluesky
            // box becomes X's, and Bluesky starts with a copy of it.
            $table->renameColumn('social_prompt_instructions', 'x_prompt_instructions');
            $table->text('bluesky_prompt_instructions')->nullable();
        });

        DB::table('projects')->update(['bluesky_prompt_instructions' => DB::raw('x_prompt_instructions')]);

        $this->copyLinkedinRows();
        $this->moveSettings();

        Schema::dropIfExists('linkedin_post_examples');
        Schema::dropIfExists('linkedin_posts');
        Schema::dropIfExists('linkedin_account_project');
        Schema::dropIfExists('linkedin_accounts');
    }

    public function down(): void
    {
        throw new RuntimeException('Merging LinkedIn into the social tables cannot be undone: restore a backup instead.');
    }

    private function copyLinkedinRows(): void
    {
        $accountIds = [];

        foreach (DB::table('linkedin_accounts')->orderBy('id')->get() as $account) {
            $accountIds[$account->id] = DB::table('social_accounts')->insertGetId([
                'organization_id' => $account->organization_id,
                'platform' => 'linkedin',
                'handle' => null,
                'external_id' => $account->member_urn,
                'display_name' => $account->display_name,
                'secret' => $account->access_token,
                'refresh_secret' => $account->refresh_token,
                'secret_expires_at' => $account->access_token_expires_at,
                'refresh_secret_expires_at' => $account->refresh_token_expires_at,
                'stats_secret' => $account->stats_access_token,
                'stats_refresh_secret' => $account->stats_refresh_token,
                'stats_secret_expires_at' => $account->stats_access_token_expires_at,
                'stats_refresh_secret_expires_at' => $account->stats_refresh_token_expires_at,
                'status' => $account->status,
                'last_error' => $account->last_error,
                'created_at' => $account->created_at,
                'updated_at' => $account->updated_at,
            ]);
        }

        foreach (DB::table('linkedin_account_project')->get() as $grant) {
            DB::table('project_social_account')->insert([
                'social_account_id' => $accountIds[$grant->linkedin_account_id],
                'project_id' => $grant->project_id,
                'created_at' => $grant->created_at,
                'updated_at' => $grant->updated_at,
            ]);
        }

        $postIds = [];

        foreach (DB::table('linkedin_posts')->orderBy('id')->get() as $post) {
            $postIds[$post->id] = DB::table('social_posts')->insertGetId([
                'project_id' => $post->project_id,
                'platform' => 'linkedin',
                'social_account_id' => $post->linkedin_account_id === null ? null : $accountIds[$post->linkedin_account_id],
                'agent_run_id' => $post->agent_run_id,
                'source_type' => $post->source_type,
                'source_ref' => $post->source_ref,
                'variant' => $post->variant,
                'evidence' => $post->evidence,
                'body' => $post->body,
                'status' => $post->status,
                'rejection_reason' => $post->rejection_reason,
                'external_id' => $post->urn,
                'url' => $post->urn === null ? null : "https://www.linkedin.com/feed/update/{$post->urn}/",
                'published_at' => $post->published_at,
                'last_error' => $post->last_error,
                'promoted_at' => $post->promoted_at,
                'likes_count' => $post->likes_count,
                'stats_checked_at' => $post->stats_checked_at,
                'created_at' => $post->created_at,
                'updated_at' => $post->updated_at,
            ]);
        }

        foreach (DB::table('linkedin_post_examples')->orderBy('id')->get() as $example) {
            DB::table('social_post_examples')->insert([
                'platform' => 'linkedin',
                'body' => $example->body,
                'source' => $example->source,
                'social_post_id' => $example->linkedin_post_id === null ? null : ($postIds[$example->linkedin_post_id] ?? null),
                'added_by_user_id' => $example->added_by_user_id,
                'created_at' => $example->created_at,
                'updated_at' => $example->updated_at,
            ]);
        }
    }

    /**
     * One like-count bar per network that reads its numbers, keyed
     * `social_examples.{platform}.min_likes`. The LinkedIn writer's own
     * model mapping goes: `social-post-writer` writes for every network now.
     *
     * The settings cache is NOT flushed here: Redis is not guaranteed
     * reachable while migrations run. `deploy/entrypoint.sh` forgets it right
     * after `migrate`, once Redis is up.
     */
    private function moveSettings(): void
    {
        DB::table('settings')->where('key', 'linkedin_examples.min_likes')->update(['key' => 'social_examples.linkedin.min_likes']);
        DB::table('settings')->where('key', 'social_examples.min_likes')->update(['key' => 'social_examples.bluesky.min_likes']);
        DB::table('settings')->where('key', 'agents.linkedin-post-writer')->delete();
    }
};
