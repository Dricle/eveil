<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every Reddit permalink the opportunity triage turned down, so the next
 * scan does not pay to turn it down again. `reddit_replies` alone only
 * remembers the threads that got a draft: an evergreen "best X" search
 * returns the same results every time, and without this table each scan
 * re-fetched and re-triaged the same rejected threads forever.
 *
 * Accepted threads are not stored here: they get a `reddit_replies` row
 * once drafted, and one whose drafting failed must come back next scan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reddit_triaged_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            // A post or a comment permalink, the same key a candidate carries.
            $table->string('permalink');
            // Refreshed on every rejection; older than the TTL means the
            // thread may be judged again (`RedditTriagedThread::TTL_DAYS`).
            $table->timestamp('triaged_at');
            $table->timestamps();

            $table->unique(['project_id', 'permalink']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reddit_triaged_threads');
    }
};
