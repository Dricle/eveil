<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The quality bar a campaign step's own track record has to clear before it
 * is trusted as a "proven" example: real volume, a reply rate worth
 * repeating, and a clean enough record that it was not just aggressive copy
 * that got lucky once.
 *
 * Three flat keys, not one `email_examples` blob: unlike `discovery`'s four
 * budgets ("spent against each other inside a single run"), these three have
 * no such coupling - each is an independent dial, same shape as
 * `crawl.max_pages`/`crawl.delay_ms`/`crawl.cache_ttl_days`.
 */
return new class extends Migration
{
    /** @var array<string, mixed> */
    private const DEFAULTS = [
        'email_examples.min_sends' => 20,
        'email_examples.min_positive_rate' => 0.10,
        'email_examples.max_unsubscribe_rate' => 0.02,
    ];

    public function up(): void
    {
        foreach (self::DEFAULTS as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => json_encode($value), 'is_encrypted' => false, 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', array_keys(self::DEFAULTS))->delete();
    }
};
