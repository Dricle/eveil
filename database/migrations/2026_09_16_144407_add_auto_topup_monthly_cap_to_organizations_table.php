<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A safety valve on auto top-up alone, not on total spend: pay-as-you-go
 * top-ups a human clicks are already a deliberate action, but an
 * unattended off-session charge on every low-balance debit has no ceiling
 * today beyond the card's own limit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->unsignedInteger('auto_topup_monthly_cap_cents')->nullable();

            // Running total of auto top-up charges in `auto_topup_spent_month`.
            // Reset lazily (compared, not cron'd) the next time a charge is
            // attempted in a new calendar month - see
            // `Organization::currentMonthAutoTopUpSpendCents()`.
            $table->unsignedInteger('auto_topup_spent_cents')->default(0);
            $table->date('auto_topup_spent_month')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'auto_topup_monthly_cap_cents',
                'auto_topup_spent_cents',
                'auto_topup_spent_month',
            ]);
        });
    }
};
