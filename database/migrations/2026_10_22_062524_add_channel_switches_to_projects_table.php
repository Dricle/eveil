<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email and ads as channels the user can switch off, so a project can run on
 * Reddit or LinkedIn alone. The other channels already had a switch: their
 * cadence.
 *
 * Email off only pauses the automatic lead search: target profiles stay, and
 * campaigns already running carry on. Ads off stops the agent and pauses every
 * campaign Eveil runs, since a switch that leaves money being spent is not off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('email_enabled')->default(true)->after('email_autonomy_level');
            $table->boolean('ads_enabled')->default(true)->after('ads_autonomy_level');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['email_enabled', 'ads_enabled']);
        });
    }
};
