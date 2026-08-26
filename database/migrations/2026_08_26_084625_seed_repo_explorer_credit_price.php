<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Priced high and deliberately: a full agentic read of a repo, tens of tool
 * round trips on Opus, is the most expensive single action in the app.
 * `repo-analyst`'s one-shot 8-file read sits at 8 credits; this can run
 * that many tool calls before it even starts writing its answer.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('credit_prices')->insert([
            'agent' => 'repo-explorer',
            'credits' => 600,
            'effective_from' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('credit_prices')->where('agent', 'repo-explorer')->delete();
    }
};
