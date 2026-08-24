<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The throttle on continuous discovery: null means uncapped. `daily_lead_limit`
 * pauses discovery and contact-finding for the rest of the day once as many new
 * leads have been discovered today; `lead_limit` stops them permanently once the
 * project has ever discovered that many. Both count every lead on the project,
 * whatever found it: the cap is "how many people does this project have", not
 * "how many did the scheduler add".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedInteger('daily_lead_limit')->nullable()->after('autonomy_level');
            $table->unsignedInteger('lead_limit')->nullable()->after('daily_lead_limit');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['daily_lead_limit', 'lead_limit']);
        });
    }
};
