<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `repo-explorer` shipped after `seed_default_settings`, so it would
 * otherwise fall back to `AgentSettings::DEFAULT` (Haiku, 60s) — too tight a
 * timeout for a run that can make dozens of sequential tool round trips
 * before it answers. Opus, same tier as the other generative agents, on a
 * 600s budget: longer than their 300s since this one's cost is the sum of
 * many small GitHub fetches, not one big generation.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'agents.repo-explorer'],
            [
                'value' => json_encode(['provider' => 'anthropic', 'model' => 'claude-opus-5', 'timeout' => 600]),
                'is_encrypted' => false,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'agents.repo-explorer')->delete();
    }
};
