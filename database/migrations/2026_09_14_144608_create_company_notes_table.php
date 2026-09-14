<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A free-text timeline entry on a company - the account-level equivalent of
 * `lead_notes`. Two tables rather than one polymorphic one, same reasoning
 * as `OutreachStatus` staying one vocabulary copied across two models
 * instead of one shared row: a lead and a company are still two different
 * relations underneath (`lead_id` vs `company_id`), and nothing here needs
 * to query "every note regardless of what it is on" in one place.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('company_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // Survives the account that wrote it, same as `lead_notes.user_id`.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_notes');
    }
};
