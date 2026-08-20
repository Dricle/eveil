<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One node of a discovery run's job graph. The row IS the replay button: it
 * carries everything the node needs to run again on its own, plus what it cost
 * and how it failed.
 *
 * Deliberately not Laravel's `jobs` table, which drops the row on success and
 * so can back neither a history, nor a cost breakdown, nor a re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discovery_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discovery_run_id')->constrained()->cascadeOnDelete();

            // Which node ran this: what it read, and what the parent handed it.
            $table->foreignId('parent_id')->nullable()->constrained('discovery_tasks')->cascadeOnDelete();

            $table->string('kind');   // plan|probe|harvest|qualify
            $table->string('status'); // pending|running|succeeded|failed|skipped

            // What a rerun needs, and nothing else: ids and queries, never a
            // page body. That is what keeps the token cost flat across a run
            // instead of quadratic.
            $table->jsonb('payload')->nullable();

            // Shape: array<string, mixed> counters this node produced. Found, harvested, qualified
            $table->jsonb('result')->nullable();

            // The model call this node made, when it made one. Most do not.
            $table->foreignId('agent_run_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            // Answers the two questions asked constantly: what is still open on
            // this run, and what does its graph look like.
            $table->index(['discovery_run_id', 'status']);
        });

        Schema::table('discovery_runs', function (Blueprint $table) {
            // Budget counters live in columns, not in the `stats` payload:
            // several workers spend the same budget at once, and only a column
            // can be incremented atomically. `stats` stays the summary written
            // when the run closes.
            $table->unsignedInteger('queries_used')->default(0)->after('budget');
            $table->unsignedInteger('candidates_found')->default(0)->after('queries_used');
            $table->unsignedInteger('pages_used')->default(0)->after('candidates_found');
            $table->unsignedInteger('qualified_count')->default(0)->after('pages_used');
        });
    }

    public function down(): void
    {
        Schema::table('discovery_runs', function (Blueprint $table) {
            $table->dropColumn(['queries_used', 'candidates_found', 'pages_used', 'qualified_count']);
        });

        Schema::dropIfExists('discovery_tasks');
    }
};
