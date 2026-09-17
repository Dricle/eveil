<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * How many posts/comments `RedditSource` batches into one `RedditThreadTriage`
 * call per probe - same shape as `sources.host_registry.batch`, its own key
 * since the two are tuned independently.
 */
return new class extends Migration
{
    private const KEY = 'sources.reddit.triage_batch';

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
