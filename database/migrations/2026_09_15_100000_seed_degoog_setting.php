<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * degoog runs alongside SearXNG as a second web-search source (issue #27):
 * same per-query cap shape as `sources.searxng.per_query`, its own key since
 * the two sources are tuned independently.
 */
return new class extends Migration
{
    private const KEY = 'sources.degoog.per_query';

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
