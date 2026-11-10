<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Same shape and model as `article-topic-matcher` (titles in, one verdict
 * per item out, Haiku 4.5), with a shorter list: no articles or site pages,
 * only the features already noted. 20 credits, like its sibling. Without a
 * price the cloud spend guard refuses the call.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('credit_prices')->insert([
            'agent' => 'feature-topic-matcher',
            'credits' => 20,
            'effective_from' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('credit_prices')->where('agent', 'feature-topic-matcher')->delete();
    }
};
