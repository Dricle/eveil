<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Few-shot examples for `LinkedinPostWriter`, shared across every
 * organization on purpose - same reasoning and shape as `email_examples`:
 * a post's text with no lead's name in it is not the tenant's private data
 * the way a lead or a reply is.
 *
 * Fed by exactly two trusted sources, never by a plain user click:
 * a superadmin typing one in directly, or `FetchLinkedinPostStats` crossing
 * a real, externally-measured like count. A user's own "mark as successful"
 * click only ever stamps `linkedin_posts.promoted_at`, feeding that SAME
 * project's own future prompts - it never reaches this table, which is what
 * keeps a self-serve click from being a one-click poisoning vector into
 * every other tenant's prompt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('linkedin_post_examples', function (Blueprint $table) {
            $table->id();
            $table->text('body');
            $table->string('source'); // manual|promoted

            // Which post earned its place here, kept for audit rather than
            // as a hard requirement: the row itself is a copy, not a live
            // reference, so it survives the post or its project being
            // deleted.
            $table->foreignId('linkedin_post_id')->nullable()->constrained('linkedin_posts')->nullOnDelete();

            // Who added it by hand. Null for a promoted one: nobody added
            // it, the numbers did.
            $table->foreignId('added_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });

        // The poll's idempotency guard: a post already copied in must never
        // be copied twice. Partial rather than `unique()` on the column
        // directly, since Blueprint has no WHERE and a manually-added row
        // never sets this column at all.
        DB::statement('
            create unique index linkedin_post_examples_linkedin_post_id_unique
            on linkedin_post_examples (linkedin_post_id)
            where linkedin_post_id is not null
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('linkedin_post_examples');
    }
};
