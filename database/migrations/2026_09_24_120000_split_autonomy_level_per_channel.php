<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Autonomy stops being one project-wide notch and becomes one per channel.
 * The old column is renamed rather than copied, so every project keeps the
 * email setting it already had. LinkedIn starts supervised: nothing may
 * start publishing under someone's name because a deploy happened.
 * Reddit has no column at all: it never posts on its own, by design.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->renameColumn('autonomy_level', 'email_autonomy_level');
        });

        Schema::table('projects', function (Blueprint $table) {
            // supervised|autonomous. No semi_auto: publishing is the only
            // step LinkedIn has, so there is nothing for a middle notch to
            // hand over.
            $table->string('linkedin_autonomy_level')->default('supervised')->after('email_autonomy_level');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('linkedin_autonomy_level');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->renameColumn('email_autonomy_level', 'autonomy_level');
        });
    }
};
