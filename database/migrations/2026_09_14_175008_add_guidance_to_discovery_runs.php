<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A free-text steer the user gave THIS run, through Evie - "look at
 * wholesalers instead of end users", "focus on the northern half of the
 * country". Read by `Planner::plan()` and handed to `DiscoveryPlanner`
 * verbatim: never baked into the agent's own instructions, which must stay
 * free of anything that reads as an example to imitate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discovery_runs', function (Blueprint $table) {
            $table->text('guidance')->nullable()->after('origin');
        });
    }

    public function down(): void
    {
        Schema::table('discovery_runs', function (Blueprint $table) {
            $table->dropColumn('guidance');
        });
    }
};
