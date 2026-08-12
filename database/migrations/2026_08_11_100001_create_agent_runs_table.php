<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every agent invocation writes a row here (ADR-004). This table is
 * simultaneously the debug log, the analysis history and the billing meter, and
 * it exists in BOTH editions — only the credit ledger is cloud-only.
 *
 * Retention is split (ADR-018): `input`/`output` carry names and emails and are
 * purged or anonymised at 90 days, while the metrics survive indefinitely so
 * billing history stays intact. `payloads_purged_at` records that the payloads
 * were dropped rather than never written.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agent_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            // Slug of the agent class: website-analyst, icp-deriver, …
            // Per agent, not per category, so this joins the credit grid, which
            // bills per action (ADR-019).
            $table->string('agent');
            $table->string('status'); // pending|running|succeeded|failed|aborted

            $table->string('provider')->nullable();
            $table->string('model')->nullable();

            $table->jsonb('input')->nullable();
            $table->jsonb('output')->nullable();
            $table->timestamp('payloads_purged_at')->nullable();

            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            $table->decimal('cost', 12, 6)->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error')->nullable();

            $table->timestamps();

            $table->index(['project_id', 'created_at']);
            $table->index(['agent', 'created_at']);

            // Drives the payload purge sweep without scanning the whole table.
            $table->index('created_at', 'agent_runs_purge_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_runs');
    }
};
