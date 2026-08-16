<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A profile can describe whoever already touches the customer rather than the
 * customer, which is often the only reachable way into a market whose
 * businesses publish a phone number and nothing else. Discovery treats both the
 * same way; the outreach written from them differs in depth.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('target_profiles', function (Blueprint $table) {
            $table->string('type')->default('customer')->after('name'); // customer|partner
        });
    }

    public function down(): void
    {
        Schema::table('target_profiles', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
