<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            // Set when the owners were told the balance ran dry, cleared by
            // the next grant: one email per depletion, not one per refused
            // agent call. Claimed atomically by `Organization::claimOutOfCreditNotice()`.
            $table->timestamp('out_of_credit_notified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('out_of_credit_notified_at');
        });
    }
};
