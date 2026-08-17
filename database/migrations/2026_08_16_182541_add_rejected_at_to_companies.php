<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Not this one" — the user's own verdict, which outranks any score.
 *
 * On the company and not on the evaluation, because rejecting is a decision
 * about the business rather than about how well it matched one profile: a
 * competitor, a former employer or a firm somebody already knows is not worth
 * writing to under any profile. Scoped by project like everything else, since
 * two projects may reasonably disagree about the same company.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->timestamp('rejected_at')->nullable()->after('discovered_at');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('rejected_at');
        });
    }
};
