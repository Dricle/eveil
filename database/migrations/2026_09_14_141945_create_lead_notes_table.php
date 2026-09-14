<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A free-text timeline entry on a lead - "called today, booked a meeting for
 * the 20th" - never sent anywhere and never read by an agent.
 *
 * No `project_id` of its own, same as `messages`: scoped through `lead_id`,
 * and `Lead` already carries `BelongsToProject`.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lead_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            // Survives the account that wrote it, same as `email_examples.added_by_user_id`.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['lead_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_notes');
    }
};
