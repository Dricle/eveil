<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Estimated, not measured, like `listing-extractor` and its neighbours in
 * `create_credit_billing_tables`: one alternate mail for one step is roughly
 * a third of what `sequence-writer` produces in one call.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('credit_prices')->insert([
            'agent' => 'variant-writer',
            'credits' => 40,
            'effective_from' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('credit_prices')->where('agent', 'variant-writer')->delete();
    }
};
