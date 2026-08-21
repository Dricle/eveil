<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The user's go-ahead on a company, which is what lets the people found there
 * enter a sequence without anybody clicking again.
 *
 * A column and not a status, deliberately. Statuses travel by COPY from a
 * company to its people, and a copy cannot reach a row that does not exist yet:
 * a contact found next week at an approved company would be born unapproved.
 * Read through the relation instead, the way `Lead::contactable()` already
 * reads its company's status.
 *
 * Nor is it a second way to exclude, which would be the mistake `status`
 * already warns about. It is a permission, in the other direction: outreach
 * needs the company approved AND not excluded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('status');

            // Every enrolment pass filters on it, on the hot side of a query
            // that runs every few minutes per active campaign.
            $table->index(['project_id', 'approved_at']);
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'approved_at']);
            $table->dropColumn('approved_at');
        });
    }
};
