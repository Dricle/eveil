<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Where a company stands with the user, in their own words rather than in a
 * score: a business they already sell to, one they closed, one they lost, one
 * they threw out. All four mean the same thing to the sender: do not write to
 * them. Which is why this replaces `rejected_at` instead of sitting beside it.
 *
 * Two ways to exclude a company would mean two queries to keep in step, and the
 * one somebody forgets is the one that mails an existing client a cold pitch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // new|contacted|client|won|lost|rejected
            $table->string('status')->default('new')->after('discovered_at');
        });

        DB::table('companies')->whereNotNull('rejected_at')->update(['status' => 'rejected']);

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('rejected_at');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->timestamp('rejected_at')->nullable()->after('discovered_at');
        });

        DB::table('companies')->where('status', 'rejected')->update(['rejected_at' => now()]);

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
