<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Few-shot examples for every agent that writes an outreach email, shared
 * across every organization on purpose - same shape as `known_hosts` or the
 * disposable-domain list: data that is legitimately instance-wide even on
 * cloud, because a subject/body pair with no lead's name in it is not the
 * tenant's private data the way a lead or a reply is.
 *
 * No `project_id`: unlike everything under `App\Models\Concerns\
 * BelongsToProject`, this is deliberately visible instance-wide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_examples', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->text('body');
            $table->string('source'); // manual|campaign

            // Which template earned its place here, kept for audit rather
            // than as a hard requirement: the row itself is a copy, not a
            // live reference, so it survives the step being edited or the
            // whole campaign being deleted.
            $table->foreignId('step_variant_id')->nullable()->constrained('step_variants')->nullOnDelete();

            // Who pasted or uploaded it by hand. Null for a promoted one:
            // nobody added it, the numbers did.
            $table->foreignId('added_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });

        // The daily promotion job's idempotency guard: a variant already
        // copied in must never be copied twice. Partial rather than
        // `unique()` on the column directly, since Blueprint has no WHERE and
        // a manually-added row never sets this column at all.
        DB::statement('
            create unique index email_examples_step_variant_id_unique
            on email_examples (step_variant_id)
            where step_variant_id is not null
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('email_examples');
    }
};
