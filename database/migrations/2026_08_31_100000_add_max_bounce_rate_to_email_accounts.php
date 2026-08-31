<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A per-mailbox override of the instance-wide bounce ceiling, null by
 * default so the account falls back to `sending.max_bounce_rate`.
 *
 * Scoped to the MAILBOX, not the project: `DispatchDueSends` runs the
 * circuit breaker on the mailbox, and one mailbox can be granted to several
 * projects. A project-level override would let one project quietly loosen
 * bounce protection on a mailbox another project depends on. The mailbox is
 * the thing whose reputation is actually at stake, so its owner is who gets
 * to decide how much risk to accept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_accounts', function (Blueprint $table) {
            $table->decimal('max_bounce_rate', 3, 2)->nullable()->after('daily_limit');
        });
    }

    public function down(): void
    {
        Schema::table('email_accounts', function (Blueprint $table) {
            $table->dropColumn('max_bounce_rate');
        });
    }
};
