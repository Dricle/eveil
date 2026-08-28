<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `repo-analyst` shipped after the original credit-price grid
 * (`create_credit_billing_tables`), so it would otherwise run unpriced -
 * which `CreditSpendGuard::refusal()` treats as a config bug and refuses to
 * run at all, never a free ride. Priced with `contact-extractor`, the
 * closest thing already in the grid: cheap-tier extraction over a similarly
 * small amount of text.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('credit_prices')->insert([
            'agent' => 'repo-analyst',
            'credits' => 8,
            'effective_from' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('credit_prices')->where('agent', 'repo-analyst')->delete();
    }
};
