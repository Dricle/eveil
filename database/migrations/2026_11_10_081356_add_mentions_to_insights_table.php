<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An article idea becomes a topic: every Reddit thread asking the same
 * question lands on one row instead of one row per thread. `mentions` is how
 * many threads asked, which is what ranks the topics for the article
 * cadence; the threads themselves go in `details.threads`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insights', function (Blueprint $table) {
            $table->unsignedInteger('mentions')->default(1);
        });

        // Every existing idea is one thread: its own.
        DB::statement(<<<'SQL'
            UPDATE insights
            SET details = jsonb_build_object('threads', jsonb_build_array(jsonb_build_object('title', title, 'url', url)))
            WHERE kind = 'article' AND url IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        DB::table('insights')->where('kind', 'article')->update(['details' => null]);

        Schema::table('insights', function (Blueprint $table) {
            $table->dropColumn('mentions');
        });
    }
};
