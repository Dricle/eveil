<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the Website agent produces, and what the Sales agent targets with.
 *
 * `project_analyses` keeps the history so a re-run can be diffed against the
 * previous one. `target_profiles` holds as many profiles as the agent derives,
 * freely editable by the user — a product usually serves several
 * markets, and flattening them into one average profile targets nobody.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /**
         * The code behind the product. Several rows per project on purpose: a
         * front end and an API are two repositories describing one product, and
         * a single column could only ever hold half the answer.
         *
         * Not `github_repositories` — the same product self-hosts on GitLab or
         * Gitea, and the provider is a property of the URL, not of the table.
         */
        Schema::create('code_repositories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            $table->string('url');
            $table->string('name');

            $table->timestamps();

            $table->unique(['project_id', 'url']);
        });

        Schema::create('project_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_run_id')->nullable()->constrained()->nullOnDelete();

            // Which repository this run read. Null for a website analysis, and
            // the reason repositories are a table: with several of them, "type
            // = repo" alone no longer says what was analysed.
            $table->foreignId('code_repository_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('type');   // website|repo
            $table->string('status'); // pending|running|succeeded|partial|failed

            $table->jsonb('raw')->nullable();
            $table->jsonb('summary')->nullable();

            // Shape: array<int, array{url: string, reason: string}> pages the crawl could not read
            $table->jsonb('failures')->nullable();
            $table->text('error')->nullable();

            $table->timestamps();

            $table->index(['project_id', 'created_at']);
        });

        Schema::create('target_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            $table->string('name');

            // Shape: array{sectors?: array<int, string>, size?: string, geography?: array<int, string>, titles?: array<int, string>, technologies?: array<int, string>, signals?: array<int, string>}
            $table->jsonb('criteria');

            $table->string('source')->default('agent'); // agent|human
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['project_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('target_profiles');
        Schema::dropIfExists('project_analyses');
        Schema::dropIfExists('code_repositories');
    }
};
