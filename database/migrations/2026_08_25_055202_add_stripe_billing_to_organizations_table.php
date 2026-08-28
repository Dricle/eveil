<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cashier's customer columns, on `organizations` rather than `users`: the
 * organization is the billable entity, never a project or a user. Published
 * from `cashier-migrations` and retargeted from the package's
 * `users` default, per Cashier's own docs: a non-default Billable model needs
 * its migrations altered to match.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('stripe_id')->nullable()->index();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four', 4)->nullable();
            $table->timestamp('trial_ends_at')->nullable();

            // Pay-as-you-go, no subscription bucket to reset each period:
            // one balance, never expires. Absent on self-hosted, which never
            // writes it - `UnmeteredSpend` never even looks.
            $table->unsignedInteger('credits_balance')->default(0);

            // Auto top-up: recharge `auto_topup_amount_cents` whenever the
            // balance drops to or below `auto_topup_threshold` credits.
            // Either both are set or neither is - enforced by
            // `AutoTopUpRequest`, not a DB constraint.
            $table->unsignedInteger('auto_topup_threshold')->nullable();
            $table->unsignedInteger('auto_topup_amount_cents')->nullable();
            // Atomic claim, same pattern as `Organization::debit()`: stops
            // two agent calls that both cross the threshold within moments
            // of each other from firing two off-session charges.
            $table->timestamp('auto_topup_locked_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropIndex(['stripe_id']);

            $table->dropColumn([
                'stripe_id',
                'pm_type',
                'pm_last_four',
                'trial_ends_at',
                'credits_balance',
                'auto_topup_threshold',
                'auto_topup_amount_cents',
                'auto_topup_locked_until',
            ]);
        });
    }
};
