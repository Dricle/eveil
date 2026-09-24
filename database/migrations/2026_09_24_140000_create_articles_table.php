<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SEO articles for the project's own blog. Publishing is manual, the same
 * shape as Reddit: the user copies the article into their own CMS, then
 * gives Eveil the URL it went live at. That URL is what the next draft reads
 * to avoid writing the same article twice, and what gets fetched into the
 * shared page cache.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_run_id')->nullable()->constrained()->nullOnDelete();

            // feature|competitor|reddit_thread|client_won|news|agent_choice|manual.
            $table->string('source_type');
            // What the source points at when it points at a row: a company id
            // for client_won, a thread permalink for reddit_thread. Same role
            // as `linkedin_posts.source_ref`.
            $table->string('source_ref')->nullable();

            // Shown beside the draft, same discipline as
            // `linkedin_posts.evidence`: never empty or generic.
            $table->text('evidence');
            $table->string('title');
            $table->text('meta_description')->nullable();
            // Markdown, the one format every CMS accepts pasted.
            $table->text('body');
            // Two-letter code the article is written in.
            $table->string('language', 2)->nullable();

            // draft|published|rejected. "Published" is self-reported: the
            // user pasted it into their CMS and gave the URL back.
            $table->string('status')->default('draft');
            $table->text('rejection_reason')->nullable();
            $table->string('published_url')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'source_type', 'source_ref']);
        });

        Schema::table('projects', function (Blueprint $table) {
            // off|daily|weekly|biweekly|monthly, default off - same opt-in
            // reasoning as `linkedin_post_frequency`.
            $table->string('article_frequency')->default('off')->after('reddit_next_scan_at');
            $table->timestamp('article_next_at')->nullable()->after('article_frequency');
        });

        DB::table('settings')->updateOrInsert(
            ['key' => 'agents.article-writer'],
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
        DB::table('settings')->where('key', 'agents.article-writer')->delete();

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['article_frequency', 'article_next_at']);
        });

        Schema::dropIfExists('articles');
    }
};
