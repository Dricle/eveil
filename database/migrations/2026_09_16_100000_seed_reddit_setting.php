<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `RedditSource` reads public submissions through Arctic Shift: same
 * per-query cap shape as `sources.degoog.per_query`, its own key since the
 * source is tuned independently.
 */
return new class extends Migration
{
    private const KEY = 'sources.reddit.per_query';

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
