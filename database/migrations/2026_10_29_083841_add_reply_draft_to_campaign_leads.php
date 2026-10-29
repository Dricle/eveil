<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The answer `ReplyHandler` drafts when a reply needs the user, waiting in the
 * inbox's reply box. Never sent by itself: the user edits it and sends it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_leads', function (Blueprint $table) {
            $table->text('reply_draft')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('campaign_leads', function (Blueprint $table) {
            $table->dropColumn('reply_draft');
        });
    }
};
