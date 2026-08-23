<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The id `laravel/ai` mints for one agent invocation, kept beside our own row.
 *
 * It is the only thing that joins this table to the SDK's step and tool events,
 * which carry timings and the provider actually used but persist nowhere. It
 * also survives failover: one invocation keeps one id across every attempt.
 *
 * Nullable and unindexed on purpose: rows opened at dispatch have no id yet
 * (the prompt has not been built), and nothing looks a run up by it. It is read
 * off a row that was already found, on the way to a log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_runs', function (Blueprint $table) {
            $table->string('invocation_id')->nullable()->after('agent');
        });
    }

    public function down(): void
    {
        Schema::table('agent_runs', function (Blueprint $table) {
            $table->dropColumn('invocation_id');
        });
    }
};
