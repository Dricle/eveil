<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every agent invocation writes a row here. This table is simultaneously the
 * debug log, the analysis history and the usage meter, and it exists in BOTH
 * editions: only the credit ledger is cloud-only.
 *
 * Tokens, not money. `laravel/ai` reports usage and no provider reports a
 * price, so any cost figure here would be our own multiplication against a list
 * price that drifts: wrong quietly, and wrong in a column that looks
 * authoritative. Self-hosted users pay their provider directly and want tokens;
 * cloud users are billed in credits, which the operator calibrates from these
 * token counts against a real invoice.
 *
 * Retention is split: `input`/`output` carry names and emails and are
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

            // Slug of the agent class: website-analyst, target-profile-deriver, …
            // Per agent, not per category, so this joins the credit grid, which
            // bills per action.
            $table->string('agent');
            $table->string('status'); // pending|running|succeeded|failed|aborted

            $table->string('provider')->nullable();
            $table->string('model')->nullable();

            $table->jsonb('input')->nullable();
            $table->jsonb('output')->nullable();
            $table->timestamp('payloads_purged_at')->nullable();

            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
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
