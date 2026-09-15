<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Same per-probe cap shape as `sources.overpass.per_probe` /
 * `sources.searxng.per_query`, its own key since it is tuned independently.
 */
return new class extends Migration
{
    private const KEY = 'sources.registry.per_probe';

    private const DEFAULT = 20;

    public function up(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => self::KEY],
            ['value' => json_encode(self::DEFAULT), 'is_encrypted' => false, 'updated_at' => now(), 'created_at' => now()],
        );
    }

    public function down(): void
    {
        DB::table('settings')->where('key', self::KEY)->delete();
    }
};
