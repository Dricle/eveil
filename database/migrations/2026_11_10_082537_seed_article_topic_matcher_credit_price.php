<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Measured on a large batch: grouping 68 angles against a project's articles
 * took 7 948 input and 1 246 output tokens on Haiku 4.5, about 0.014 $. A
 * normal scan proposes a handful of angles, so 20 credits leaves margin.
 * Without a price the cloud spend guard refuses the call, and the Reddit
 * scan that makes it fails with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('credit_prices')->insert([
            'agent' => 'article-topic-matcher',
            'credits' => 20,
            'effective_from' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('credit_prices')->where('agent', 'article-topic-matcher')->delete();
    }
};
