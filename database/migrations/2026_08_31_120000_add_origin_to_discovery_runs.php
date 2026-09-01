<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A user-submitted link becomes a run too (issue #25), but it is not a signal
 * about AI search quality: `ContinueDiscovery` must not let it block or count
 * as the profile's "latest" run, and `DiscoveryRun::diagnose()` must not
 * diagnose a handful of pasted links as `too_narrow`. `origin` is what tells
 * the two apart without either reading the run's tasks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discovery_runs', function (Blueprint $table) {
            $table->string('origin')->default('search')->after('target_profile_id');
        });
    }

    public function down(): void
    {
        Schema::table('discovery_runs', function (Blueprint $table) {
            $table->dropColumn('origin');
        });
    }
};
