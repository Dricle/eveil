<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A business with no site of its own is still a business, and on a directory
 * listing it is the common case rather than the exception. `domain` was the
 * dedupe key and NOT NULL, so those rows could only ever be counted and
 * reported: the one segment nobody else is calling was the one we dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('domain')->nullable()->change();
            $table->dropUnique('companies_project_id_domain_unique');
        });

        // Postgres treats every NULL as distinct, so the plain unique index
        // would stop deduping the moment the column became nullable.
        DB::statement('CREATE UNIQUE INDEX companies_project_id_domain_unique ON companies (project_id, domain) WHERE domain IS NOT NULL');

        // Without a domain the name is the only key every source supplies. Two
        // businesses sharing a name in different towns therefore collapse into
        // one row: losing one prospect is cheaper than writing to the same
        // person twice, and the phone would separate them the day a directory
        // publishes one often enough to rely on.
        DB::statement('CREATE UNIQUE INDEX companies_project_id_name_unique ON companies (project_id, lower(name)) WHERE domain IS NULL');
    }

    public function down(): void
    {
        // The rows this migration exists for cannot survive the column going
        // back to NOT NULL.
        DB::table('companies')->whereNull('domain')->delete();

        DB::statement('DROP INDEX companies_project_id_domain_unique');
        DB::statement('DROP INDEX companies_project_id_name_unique');

        Schema::table('companies', function (Blueprint $table) {
            $table->string('domain')->nullable(false)->change();
            $table->unique(['project_id', 'domain']);
        });
    }
};
