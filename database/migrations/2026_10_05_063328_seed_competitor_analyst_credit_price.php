<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Estimated, not measured: same model and same 60k-character input budget
 * as `website-analyst`, so the same price until real runs say otherwise.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('credit_prices')->insert([
            'agent' => 'competitor-analyst',
            'credits' => 200,
            'effective_from' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('credit_prices')->where('agent', 'competitor-analyst')->delete();
    }
};
