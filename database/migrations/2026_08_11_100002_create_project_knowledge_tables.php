<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the Website agent produces, and what the Sales agent targets with.
 *
 * `project_analyses` keeps the history so a re-run can be diffed against the
 * previous one (story 4.2). `icps` holds as many profiles as the agent derives,
 * freely editable by the user (ADR-015) — a product usually serves several
 * markets, and flattening them into one average profile targets nobody.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('project_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_run_id')->nullable()->constrained()->nullOnDelete();

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

        Schema::create('icps', function (Blueprint $table) {
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
        Schema::dropIfExists('icps');
        Schema::dropIfExists('project_analyses');
    }
};
