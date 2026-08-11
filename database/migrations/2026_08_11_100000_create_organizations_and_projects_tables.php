<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenancy skeleton — three separate permission scopes (ADR-003):
 * instance (`users.is_super_admin`), organization (`organization_user.role`),
 * project (`project_user`, a plain access grant with no role of its own).
 *
 * Status-like columns are plain strings cast to PHP enums in the models rather
 * than `enum()`: on PostgreSQL that would generate a CHECK constraint needing a
 * drop-and-recreate for every new value, on a schema we know will move.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('password');
        });

        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('organization_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member'); // owner|admin|member
            $table->timestamps();

            $table->unique(['organization_id', 'user_id']);
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url');
            $table->string('github_repo')->nullable();

            // Product portrait built by the Website agent, user-editable (ADR-021
            // reads `default_language` from here as the last fallback).
            $table->jsonb('knowledge_base')->nullable();
            $table->boolean('knowledge_base_edited_by_user')->default(false);
            $table->string('default_language', 5)->nullable();

            // supervised|semi_auto|autonomous — default semi_auto (ADR-009).
            $table->string('autonomy_level')->default('semi_auto');

            $table->timestamps();

            $table->index(['organization_id', 'name']);
        });

        Schema::create('project_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_user');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('organization_user');
        Schema::dropIfExists('organizations');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_super_admin');
        });
    }
};
