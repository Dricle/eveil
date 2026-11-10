<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The newest GitHub release already announced, so each one becomes a post
 * once. Null until the first check, which only records it: a repo linked
 * today must not announce a release from last year.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('code_repositories', function (Blueprint $table) {
            $table->unsignedBigInteger('last_release_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('code_repositories', function (Blueprint $table) {
            $table->dropColumn('last_release_id');
        });
    }
};
