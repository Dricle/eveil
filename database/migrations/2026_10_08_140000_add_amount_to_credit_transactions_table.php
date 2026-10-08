<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ledger recorded how many credits a purchase granted but never what was
 * paid for them, which made revenue unrecoverable from our own database: the
 * amount can only be inferred by multiplying credits back through whatever
 * `billing.credits_per_dollar` happened to be at the time, and that rate is
 * explicitly allowed to move. A top-up bought at an old rate would be valued
 * at today's and quietly report the wrong number.
 *
 * Nullable, and deliberately not backfilled. A debit and a trial grant have no
 * money behind them at all, so null is the honest value rather than a zero
 * that averages into reports. Rows written before this migration stay null and
 * are counted separately by `eveil:revenue` instead of being guessed at.
 *
 * Currency is stored per row, not assumed: `Organization::preferredCurrency()`
 * already decides it per customer, so summing cents across rows without it
 * would add euros to dollars.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table): void {
            $table->unsignedInteger('amount_cents')->nullable()->after('credits');
            $table->string('currency', 3)->nullable()->after('amount_cents'); // ISO 4217, lowercase as Stripe returns it

            // Revenue is always read as "purchases, over a date range", and
            // the ledger is dominated by debits: one agent call per row.
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table): void {
            $table->dropIndex(['type', 'created_at']);
            $table->dropColumn(['amount_cents', 'currency']);
        });
    }
};
