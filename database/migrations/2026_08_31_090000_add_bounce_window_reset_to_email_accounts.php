<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where the bounce circuit breaker's rolling window starts counting from.
 *
 * Without this, reactivating a paused mailbox is a no-op the moment its
 * all-time last-100 sends are still over the bounce threshold: the very next
 * dispatch tick replays the same stale history and re-pauses it before a
 * single new mail can leave to dilute the rate. Every operator action that
 * says "this mailbox is fine now" (a manual reactivate, a passing connection
 * test) resets this to the moment of that decision, so `recentBounceRate()`
 * only ever judges what happened SINCE somebody looked.
 *
 * Null on existing rows and on ordinary creation: nothing has been decided
 * about them yet, so the breaker keeps reading all-time history exactly as
 * before, unchanged until the first explicit reactivation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_accounts', function (Blueprint $table) {
            $table->timestamp('bounce_window_reset_at')->nullable()->after('last_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('email_accounts', function (Blueprint $table) {
            $table->dropColumn('bounce_window_reset_at');
        });
    }
};
