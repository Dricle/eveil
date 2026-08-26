<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Without this, `RepoReader` can only read public repositories: GitHub
 * answers 404 for a private one to an unauthenticated request, which is
 * indistinguishable from "does not exist". One token per project, not per
 * repository: the same PAT the user pastes in almost always covers every
 * repo they would ever link here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->text('github_token')->nullable()->after('url'); // encrypted with CREDENTIALS_KEY
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn('github_token');
        });
    }
};
