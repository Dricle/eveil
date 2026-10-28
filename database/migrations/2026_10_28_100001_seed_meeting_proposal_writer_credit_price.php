<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Estimated, not measured: one short mail on the small default model, the
 * same shape of call as `message-personalizer`, so the same price. Without a
 * row the cloud spend guard refuses the agent outright.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('credit_prices')->insert([
            'agent' => 'meeting-proposal-writer',
            'credits' => 3,
            'effective_from' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('credit_prices')->where('agent', 'meeting-proposal-writer')->delete();
    }
};
