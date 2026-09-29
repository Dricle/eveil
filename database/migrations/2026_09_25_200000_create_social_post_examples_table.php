<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One shared bank of proven posts per network, same trust rule as
 * `linkedin_post_examples`: only a superadmin's own hand or a real,
 * externally-measured like count writes here, never a user's "mark as
 * successful" click. X has no automatic way in: its numbers are never read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_post_examples', function (Blueprint $table) {
            $table->id();
            // x|bluesky.
            $table->string('platform');
            $table->text('body');
            // manual|promoted.
            $table->string('source');
            // Which post earned its place, kept for audit: the row is a copy
            // and survives the post being deleted.
            $table->foreignId('social_post_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('added_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('platform');
        });

        // The stats poll's idempotency guard, same as LinkedIn's: a post
        // already copied in must never be copied twice.
        DB::statement('
            create unique index social_post_examples_social_post_id_unique
            on social_post_examples (social_post_id)
            where social_post_id is not null
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('social_post_examples');
    }
};
