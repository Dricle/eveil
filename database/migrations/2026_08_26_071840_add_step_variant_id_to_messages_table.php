<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which template actually produced this send. Without it, a sent message
 * can be traced back to the lead and the campaign, but not to which of a
 * step's variants was rewritten for them — the thing a "does this template
 * actually work" measurement needs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('step_variant_id')->nullable()->after('campaign_lead_id')
                ->constrained('step_variants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('step_variant_id');
        });
    }
};
