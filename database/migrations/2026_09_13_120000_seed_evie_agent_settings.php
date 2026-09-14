<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `evie` ships after `seed_default_settings`, so it would otherwise fall
 * back to `AgentSettings::DEFAULT` (Haiku, 60s) - too tight a timeout for an
 * orchestrator that plans, calls tools and streams a long answer. Opus, same
 * tier as the other generative agents, on a 300s budget matching
 * `website-analyst`/`discovery-planner`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'agents.evie'],
            [
                'value' => json_encode(['provider' => 'anthropic', 'model' => 'claude-opus-5', 'timeout' => 300]),
                'is_encrypted' => false,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'agents.evie')->delete();
    }
};
