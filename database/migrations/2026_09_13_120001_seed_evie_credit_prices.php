<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `evie` bills per conversation depth, not a flat price per call
 * (`EveilAgent::billingKey()`): a short chat and a long one cost genuinely
 * different amounts in provider tokens, since the whole history is resent
 * every turn. Three placeholder tiers, not measured yet, same "still a
 * guess" caveat as every other new agent's first price in this grid.
 *
 * These do not show up on `/app/app-settings/agents` (that screen looks up
 * one price by the plain agent slug) - repricing them is a new migration or
 * tinker, deliberately, since this is a cloud-operator-only concern.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('credit_prices')->insert([
            ['agent' => 'evie:tier-1', 'credits' => 1, 'effective_from' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['agent' => 'evie:tier-2', 'credits' => 2, 'effective_from' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['agent' => 'evie:tier-3', 'credits' => 3, 'effective_from' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        DB::table('credit_prices')->whereIn('agent', [
            'evie:tier-1',
            'evie:tier-2',
            'evie:tier-3',
        ])->delete();
    }
};
