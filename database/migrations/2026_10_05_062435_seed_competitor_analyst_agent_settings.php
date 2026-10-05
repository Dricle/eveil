<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `competitor-analyst` ships after `seed_default_settings`, so it would
 * otherwise fall back to `AgentSettings::DEFAULT` (Haiku, 60s): too tight for
 * reading three competitor sites' worth of text, and a judgment ("does the
 * product already have this?") a small model gets wrong. Same tier and
 * budget as `website-analyst`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'agents.competitor-analyst'],
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
        DB::table('settings')->where('key', 'agents.competitor-analyst')->delete();
    }
};
