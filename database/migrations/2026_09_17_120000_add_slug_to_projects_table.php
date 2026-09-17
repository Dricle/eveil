<?php

use App\Models\Project;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
        });

        // Backfill through the model, not raw SQL: `HasSlug` already owns the
        // slugify-and-de-duplicate logic (`app/Models/Concerns/HasSlug.php`),
        // and reusing it here means that logic exists in exactly one place.
        Project::query()->whereNull('slug')->cursor()->each(fn (Project $project) => $project->save());

        Schema::table('projects', function (Blueprint $table) {
            $table->string('slug')->unique()->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
